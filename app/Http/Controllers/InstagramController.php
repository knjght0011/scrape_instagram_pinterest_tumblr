<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Validator;
use InstagramScraper\Instagram;

class InstagramController extends Controller
{
    public function index()
    {
        return view('instagram.index');
    }

    public function handleSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hashtag' => 'required',
            'directory_input' => 'required',
            'limit' => 'numeric|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->errors()
            ), 403);
        }

        $hashtag = $request->get('hashtag');
//        $client = new \GuzzleHttp\Client();

        try {

//            $res = $client->request('GET', "https://api.instagram.com/v1/tags/$hashtag/media/recent?client_id=$INSTAGRAM_ACCESS_TOKEN");
            $limit = $request->get('limit', 10);
            $medias = Instagram::getMedias($hashtag, $limit, '');
            $res = json_encode($medias);
            if ($res) {
                $res = json_decode($res);
                $directory_input = $request->get('directory_input', '');
                return $this->handleImagesData($res, $directory_input, $hashtag, $limit);
            }
        } catch (ClientException $exception) {
            return response()->json(
                [
                    'success' => 0,
                    'message' => 'Images not found',
                    "total" => 0
                ]
            );
        }

    }

    public function handleImagesData($ins, $directory_input, $hashtag, $limit)
    {
        $folder_name = createFolder();
        $images = [];
        $tag_slug = str_slug($hashtag);
        $i = 0;
        $j = 1;
        while ($j <= $limit && isset($ins[$i])) {
            $in = $ins[$i];
            if ($in->imageStandardResolutionUrl) {
                $image_url = str_replace('http://', '', $in->imageStandardResolutionUrl);;
                $type = explode('.', $image_url)[count(explode('.', $image_url)) - 1];
                $image_name = $tag_slug . date('-Y-m-d-', time()) . $i . '.' . $type;
                handleSaveImage($folder_name, $in->imageStandardResolutionUrl, $image_name);
                $image_caption = cleanSpecialChar($in->caption);
                $images[] = [
                    'image_url' => $image_url,
                    'caption' => $image_caption,
                    'image_name'=> $image_name,
                ];
                $j ++;
            }
            $i ++;
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

