<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Validator;


/*
 * search
 *
    $html = new Htmldom('https://www.pinterest.com/search/pins/?q=hoa%20hong');
    $images = [];
    foreach ($html->find('img') as $image) {
        $image_info = $image->attr;
        $images [] = [
            'url'=>$image_info['src'],
            'cation'=>$image_info['alt']
        ];
    }
 *
 * */


class PinterestController extends Controller
{
    public function index()
    {
        return view('pinterest.index');
    }


    public function handleSubmit(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'board' => 'required',
            'directory_input' => 'required',
            'limit' => 'numeric|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->errors()
            ), 403);
        }


        $username = $request->get('username');
        $board = $request->get('board');


        $client = new \GuzzleHttp\Client();

        try {
//            $res = $client->request('GET', "https://api.pinterest.com/v3/pidgets/boards/$username/$board/pins/");
            $limit = $request->get('limit', 10);
            $url = "https://api.pinterest.com/v1/boards/" . $username . "/" . $board . "/pins/?access_token=" . env('PINTEREST_ACCESS_TOKEN') . "&limit=" . $limit . "&fields=id,link,counts,note,url,image";
            $directory_input = $request->get('directory_input', '');
            if($limit > 100) {
                $url = "https://api.pinterest.com/v1/boards/" . $username . "/" . $board . "/pins/?access_token=" . env('PINTEREST_ACCESS_TOKEN') . "&limit=" . 100 . "&fields=id,link,counts,note,url,image";
                (int)$quotient = (int) $limit/100;
                $surplus = $limit % 100;
                $data = [];
                $i = 1;
                while ($i <= $quotient && $url != null) {
                    $res = $client->request('GET', $url);
                    $data = array_merge($data, json_decode($res->getBody(), true)['data']);
                    $url = json_decode($res->getBody(), true)['page']['next'];
                    $i ++;
                }
                if($url != null && $surplus != 0) {
                    $res = $client->request('GET', $url);
                    for ($i = 0; $i < $surplus; $i ++) {
                        $data[] = json_decode($res->getBody(), true)['data'][$i];
                    }
                }
                return $this->handleImagesData($data, $directory_input, $username . $board);
            }
            $res = $client->request('GET', $url);
            if ($res->getStatusCode() == 200) {
                $data = json_decode($res->getBody(), true)['data'];
                if (count($data)) {
                    return $this->handleImagesData($data, $directory_input, $username . $board);
                }
            }

        } catch (ClientException $exception) {
            return [
                'success' => 0,
                'message' => "Maximum value for 'limit' is 100",
                "total" => 0
            ];
        }

    }

    public function handleImagesData($pins, $directory_input, $tag)
    {
        $folder_name = createFolder();
        $images = [];
        $tag_slug = str_slug($tag);
        $i = 1;
        foreach ($pins as $pin) {

//            if(count($pin['images'])){
            $type = explode('.', $pin['image']['original']['url'])[count(explode('.', $pin['image']['original']['url'])) - 1];
            $image_name = $tag_slug . date('-Y-m-d-', time()) . $i . '.' . $type;
            $i ++;
            handleSaveImage($folder_name, $pin['image']['original']['url'], $image_name);

            $images[] = [
                'image_url' => $pin['image']['original']['url'],
                'caption' => cleanSpecialChar($pin['note']),
                'image_name'=> $image_name
            ];


//            }
        }

        $tmp_image_path = realpath('./uploads/' . $folder_name);
        /*
         * write excel contain path image
         * */
        writeExcel($images, $folder_name, $directory_input);
        zipData($tmp_image_path, platformSlashes(public_path('uploads/' . $folder_name . '.zip')));
        if (is_dir($tmp_image_path)) {
            delFolder($tmp_image_path);
        }
        return [
            'success' => 1,
            'total' => count($images),
            'images' => $images,
            'zip_link' => url('uploads/' . $folder_name . '.zip')
        ];
    }
}


