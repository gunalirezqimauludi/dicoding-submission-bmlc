<?php
require __DIR__ . '/vendor/autoload.php';
 
use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use LINE\LINEBot\MessageBuilder\AudioMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;
 
// set false for production
$pass_signature = true;
 
// set LINE channel_access_token and channel_secret
$channel_access_token = "EHnWRRzp0WlnRolzIGHwc8fvDQFE+QuyYLBd1jNqXef7l/gF4iWZMMXh1wcBMOCV84KwftIc/7+cpCT3AKKN/im65rwyQssWOX91OIDW2VhbUP2Oti90o8NQ39tpy1GzrN/Gq3MZ/AE1wKAaqAe/9gdB04t89/1O/w1cDnyilFU=";
$channel_secret = "15356dc202b547b3b51202579db65089";
 
// inisiasi objek bot
$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);
 
$configs =  [
    'settings' => ['displayErrorDetails' => true],
];
$app = new Slim\App($configs);
 
// buat route untuk url homepage
$app->get('/', function($req, $res)
{
  echo "Welcome at Slim Framework";
});
 
// buat route untuk webhook
$app->post('/webhook', function ($req, $res) use ($bot, $httpClient, $pass_signature)
{
    // get request body and line signature header
    $body = file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : '';
 
    // log body and signature
    file_put_contents('php://stderr', 'Body: '.$body);
 
    if($pass_signature === false)
    {
        // is LINE_SIGNATURE exists in request header?
        if (empty($signature))
        {
            return $res->withStatus(400, 'Signature not set');
        }
 
        // is this request comes from LINE?
        if (! SignatureValidator::validateSignature($body, $channel_secret, $signature))
        {
            return $res->withStatus(400, 'Invalid signature');
        }
    }
 
    $data = json_decode($body, true);
    if(is_array($data['events']))
    {
        foreach ($data['events'] as $event)
        {
            if ($event['type'] == 'follow')
            {
                if($event['source']['userId'])
                {
                    $userId     = $event['source']['userId'];
                    $getprofile = $bot->getProfile($userId);
                    $profile    = $getprofile->getJSONDecodedBody();
                    $greetings  = new TextMessageBuilder("Hi ".$profile['displayName'].", aku adalah bot yang akan membantu kamu untuk menemukan, mendengarkan, dan mengunduh lagu apa pun disini! Kirimkan nama artis atau nama lagu dan aku akan mencarikannya untukmu!");
                    $onboarding = new TextMessageBuilder('Ketik LAGU jika ingin mencari berdasarkan nama lagu dan ketik ARTIS jika ingin mencari berdasarkan nama artis');

                    $multiMessageBuilder = new MultiMessageBuilder();
                    $multiMessageBuilder->add($greetings);
                    $multiMessageBuilder->add($onboarding);

                    $result = $bot->replyMessage($event['replyToken'], $multiMessageBuilder);
                    return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                }
            } elseif ($event['type'] == 'message')
            {
                if($event['message']['type'] == 'text')
                {
                    $message = strtolower($event['message']['text']);
                    if ($message == 'lagu')
                    {
                        $textMessageBuilder = new TextMessageBuilder('Lagu apa yang kamu cari?');
                        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);

                        return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                    } elseif ($message == 'artis')
                    {
                        $textMessageBuilder = new TextMessageBuilder('Siapa Artis yang kamu cari?');
                        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);

                        return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                    } else
                    {
                        if ($message != 'download')
                        {
                            $client = new \GuzzleHttp\Client();
                            $request = $client->request('GET', 'https://botline-dicoding.herokuapp.com/music.php/search/'.$message);
                            $response = json_decode($request->getBody());

                            if ($response->status == 'false')
                            {
                                $textMessageBuilder = new TextMessageBuilder('Tidak ada hasil yang ditemukan, Coba kata kunci lain');
                                $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
                            } else
                            {
                                $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                                    'replyToken' => $event['replyToken'],
                                    'messages'   => [
                                        [
                                            'type'     => 'flex',
                                            'altText'  => 'this is a flex message',
                                            'contents' => [
                                                'type' => 'carousel',
                                                'contents' => $response->videos
                                            ]
                                        ]
                                    ],
                                ]);
                            }

                            // MANUAL
                            // $flexTemplate = file_get_contents("flex_music.json");
                            // $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                            //     'replyToken' => $event['replyToken'],
                            //     'messages'   => [
                            //         [
                            //             'type'     => 'flex',
                            //             'altText'  => 'this is a flex message',
                            //             'contents' => [
                            //                 'type' => 'carousel',
                            //                 'contents' => json_decode($flexTemplate)
                            //             ]
                            //         ]
                            //     ],
                            // ]);

                            return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                        }
                    }
                }
            } elseif ($event['type'] == 'postback')
            {
                $explodeData    = explode('&', $event['postback']['data']);
                $action         = explode('=', $explodeData[0]);

                if ($action[1] == 'download')
                {
                    $title     = explode('=', $explodeData[1]);
                    $videoId   = explode('=', $explodeData[2]);
                    $videoUrl  = explode('=', $explodeData[3]);
                    $duration  = explode('=', $explodeData[4]);

                    $links = "https://api.download-lagu-mp3.com/@download/$videoUrl[1]/mp3/$videoId[1]/$title[1].mp3";
                    
                    $audioMessageBuilder = new AudioMessageBuilder($links, $duration[1]);
                    $result = $bot->replyMessage($event['replyToken'], $audioMessageBuilder);

                    // $textMessageBuilder = new TextMessageBuilder($links);
                    // $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);

                    return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
                }
            }
        } 
    }

    return $res->withStatus(400, 'No event sent!');
});

$app->get('/profile/{userId}', function($req, $res) use ($bot)
{
    $route  = $req->getAttribute('route');
    $userId = $route->getArgument('userId');
    $result = $bot->getProfile($userId);
             
    return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
});

function jsonOnBoarding(){
    $string = '{"type":"bubble","body":{"type":"box","layout":"vertical","spacing":"sm","contents":[{"type":"text","text":"Apa yang ingin kamu cari ?","wrap":true,"weight":"bold","size":"md"}]},"footer":{"type":"box","layout":"horizontal","spacing":"sm","contents":[{"type":"button","flex":2,"style":"primary","action":{"type":"message","label":"Lagu","text":"lagu"}},{"type":"button","flex":2,"style":"primary","action":{"type":"message","label":"Artis","text":"artis"}},{"type":"spacer","size":"sm"}],"flex":0}}';
    $data = json_decode($string, true);

    return $data;
}

$app->run();