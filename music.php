<?php
require __DIR__ . '/vendor/autoload.php';

$configs =  [
    'settings' => ['displayErrorDetails' => true],
];
$app = new Slim\App($configs);

$app->get('/search/{keyword}', function($req, $res)
{
    $apiKey = '9132498C-67F1-0E71-782D-FB6FC767C6D2';
    $keyword = $req->getAttribute('keyword');
    
    $client = new \GuzzleHttp\Client();
    $request = $client->request('GET', 'https://api.w3hills.com/youtube/search?keyword='.$keyword.'&api_key='.$apiKey);
    $response = json_decode($request->getBody());

    $data = array();
    if ($response->status == 1)
    {
        $data['status'] = 'true';
        $data['videos'] = array();
        foreach ($response->videos as $index => $row) {
            $detailInfo = getDetailList($row->id);

            if ((int) $detailInfo['size'] <= 10) {

                $id         = $row->id;
                $publish    = $row->publish->owner;
                $title      = $row->title;
                $thumbnail  = $row->thumbnail;
                $duration   = gmdate("H:i:s", $row->duration);
                $size       = $detailInfo['size'];

                $explodeURL     = explode('/', $detailInfo['links']);
                $token          = $explodeURL[4];
                $filename       = str_replace(' ', '_', substr($row->title, 0, 50));
                $milliseconds   = ($row->duration * 1000);

                // JSON
                $jsonFlex = '{"type":"bubble","hero":{"type":"image","url":"'.$thumbnail.'","size":"full","aspectRatio":"20:13","aspectMode":"cover"},"body":{"type":"box","layout":"vertical","contents":[{"type":"text","text":"'.$title.'","weight":"bold","size":"xl","margin":"md"},{"type":"text","text":"'.$publish.'","size":"xs","color":"#aaaaaa","wrap":true},{"type":"separator","margin":"xxl"},{"type":"box","layout":"vertical","margin":"lg","spacing":"sm","contents":[{"type":"box","layout":"baseline","spacing":"sm","contents":[{"type":"text","text":"Duration","color":"#aaaaaa","size":"sm","flex":1},{"type":"text","text":"'.$duration.'","wrap":true,"color":"#666666","size":"sm","flex":3}]},{"type":"box","layout":"baseline","spacing":"sm","contents":[{"type":"text","text":"Size","color":"#aaaaaa","size":"sm","flex":1},{"type":"text","text":"'.$size.'","wrap":true,"color":"#666666","size":"sm","flex":3}]}]}]},"footer":{"type":"box","layout":"vertical","spacing":"sm","contents":[{"type":"button","flex":2,"style":"primary","action":{"type":"postback","label":"Download","data":"action=download&title='.$filename.'&id='.$id.'&token='.$token.'&duration='.$milliseconds.'","text":"Download"}},{"type":"spacer","size":"sm"}],"flex":0}}';
                $arrayFlex = json_decode($jsonFlex, true);
                array_push($data['videos'], $arrayFlex);

                // ARRAY
                // array_push($data['videos'], array(
                //     'id' => $id,
                //     'publish' => $publish,
                //     'title' => $title,
                //     'thumbnail' => $thumbnail,
                //     'duration' => $duration,
                //     'links' => $links,
                //     'size' => $size
                // ));
            }
        }
    } else {
        $data['status'] = 'false';
    }

    return $res->withJson($data, $request->getStatusCode());
});

function getDetailList($videoId)
{
    $client = new \GuzzleHttp\Client();
    $result = $client->request('GET', 'https://api.download-lagu-mp3.com/@api/json/mp3/'.$videoId);
    $response = json_decode($result->getBody())->vidInfo->{'4'};

    $data = array();
    $data['links'] = $response->dloadUrl;
    $data['size']  = $response->mp3size;

    return $data;
};

$app->run();