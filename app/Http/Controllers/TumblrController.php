<?php

namespace App\Http\Controllers;

use App\Libs\Tumblr\API\RequestException;
use Illuminate\Http\Request;
use App\Libs\Tumblr\API\Client;
use League\Flysystem\Exception;
use Maatwebsite\Excel\Facades\Excel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Validator;

class TumblrController extends Controller
{
    public function index()
    {
        return view('tumblr.index');
    }


    public function handleTextData($data)
    {

        return [
            'success' => 1,
            'posts' => $data,
            'total' => count($data),
            'post_type' => 'text'
        ];

    }

    public function handleImagesData($data, $directory_input, $limit, $tag)
    {
        $folder_name = createFolder();
        $images = [];
        $count_image = 1;
        $tag_slug = str_slug($tag);
        $i = 1;
        foreach ($data as $image) {
            if ($count_image <= $limit) {
                $count_image++;
                $image_url = $image['image_url'];
                /*
                 * move image to the temple folder
                 * */
                $type = explode('.', $image_url)[count(explode('.', $image_url)) - 1];
                $image_name = $tag_slug . date('-Y-m-d-', time()) . $i . '.' . $type;

                $i++;
                handleSaveImage($folder_name, $image_url, $image_name);
                $images[] = [
                    'image_url' => $image_url,
                    'image_name' => $image_name,
                    'caption' => cleanSpecialChar($image['image_name'])
                ];
            }
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
            'zip_link' => url('uploads/' . $folder_name . '.zip'),
            'post_type' => 'photo'
        ];
    }


    public function handleSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tag' => (!$request->get('blog_type')) ? 'required' : '',
            'blogname' => ($request->get('blog_type')) ? 'required' : '',
            'limit' => 'numeric|max:500'
        ]);
        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->errors()
            ), 403);
        }
        $TUMBLR_APIKEY = env('TUMBLR_APIKEY');
        if (empty($TUMBLR_APIKEY)) {
            throw new \Exception('missing the APIKEY of Tumblr');
        }
        $client = new Client($TUMBLR_APIKEY);
        $tag = $request->get('tag');
        $limit = $request->get('limit', 10);
        $offset = ($request->get('start') != '') ? $request->get('start') : 0;
        $blog_type = $request->get('blog_type');

        if ($blog_type) {
            $post_type = $request->get('post_type');
            if ($limit <= 50) {
                $info = $client->getBlogPosts($request->get('blogname'),
                    array('type' => $post_type, 'tag' => $tag, 'offset' => $offset, 'limit' => $limit));
                $infoPost = $info->posts;
                $url_blog = $info->blog->url;
                $url_blog = str_replace('https://', '', $url_blog);
            } else {
                $infoPost = [];
                (int)$quotient = (int)$limit / 50;
                $surplus = $limit % 50;
                $infoPost = [];
                $i = 1;
                while ($i <= $quotient) {
                    $info = $client->getBlogPosts($request->get('blogname'),
                        array('type' => $post_type, 'tag' => $tag, 'offset' => count($infoPost), 'limit' => 50));

                    $infoPost = array_merge($infoPost, $info->posts);
                    $i++;
                }
                if ($surplus != 0) {
                    $info = $client->getBlogPosts($request->get('blogname'),
                        array('type' => $post_type, 'tag' => $tag, 'offset' => count($infoPost), 'limit' => $surplus));
                    $infoPost = array_merge($infoPost, $info->posts);
                }
                $url_blog = $info->blog->url;
                $url_blog = str_replace('https://', '', $url_blog);
            }
            if ($post_type == 'text') {
                /*$url_get = 'https://api.tumblr.com/v2/blog/' . $url_blog . 'posts/text?api_key=fuiKNFp9vQFvjLNvx4sUwti4Yb5yGutBN4Xh10LXZhhRKjWlV4&notes_info=true';
                $json = $this->getContentFromUrl($url_get);
                $data = json_decode($json);
                $images = $data->response->posts;*/
                $result = $this->handleTextData($infoPost);
                return $result;
            } elseif ($post_type == 'audio') {
                $posts = $infoPost;
                $post_audio = [];
                if (count($posts) > 0) {
                    foreach ($posts as $post) {
                        $url_get = 'https://api.tumblr.com/v2/blog/' . $url_blog . 'posts?id=' . $post->id . '&api_key=fuiKNFp9vQFvjLNvx4sUwti4Yb5yGutBN4Xh10LXZhhRKjWlV4';
                        $json = $this->getContentFromUrl($url_get);
                        $data = json_decode($json);
                        if ($data->meta->status == 200 && count($data->response->posts) > 0) {
                            $post_audio = array_merge($post_audio, $data->response->posts);
                        }
                    }
                    $result = $this->handleTextData($post_audio);
                    return $result;
                }
            } elseif ($post_type == 'photo') {
                $posts = $infoPost;
                $post_photo = [];
                if (count($posts) > 0) {
                    foreach ($posts as $post_item) {
                        if (count($post_item->photos) > 0) {
                            foreach ($post_item->photos as $photo) {
                                $post_photo[] = [
                                    'image_url' => $photo->original_size->url,
                                    'image_name' => $photo->caption != '' ? $photo->caption : $post_item->summary
                                ];
                            }
                        }
                    }
                    $directory_input = $request->get('directory_input', '');
                    $result = $this->handleImagesData($post_photo, $directory_input, $limit, $request->get('blogname'));
                    return $result;
                }
            }
            return [
                'success' => 0,
                'message' => 'Post not found',
                "total" => 0
            ];
        } else {
            $link = 'https://api.tumblr.com/v2/tagged?tag=' . $tag . '&api_key=' . env('TUMBLR_APIKEY') . '&limit=' . $limit . '%s';
            $p = 0;
            $images = [];
            $before = '';
            $totalPost = 0;
            while (true) {
                $p++;
                $sql = sprintf($link, $before);
                $json = $this->getContentFromUrl($sql);
                $data = json_decode($json);

                if (isset($data->response) && count($data->response) > 0) {
                    $totalPost = count($data->response);
                    for ($i = 0; $i < $totalPost; $i++) {
                        if ($data->response[$i]->type == 'photo') {
                            $images[] = [
                                'image_url' => $data->response[$i]->photos[0]->original_size->url,
                                'image_name' => $data->response[$i]->caption != '' ? $data->response[$i]->caption : $data->response[$i]->blog_name
                            ];
                        }
                    }
                }
                if ($totalPost > 0) {
                    try {
                        $before = '&before=' . $data->response[$totalPost - 1]->timestamp;
                    } catch (\Exception $ex) {
                        break;
                    }
                }
                usleep(300);
                if ($p == 20) {
                    break;
                }
            }
            if (count($images) > 0) {
                $directory_input = $request->get('directory_input', '');
                $result = $this->handleImagesData($images, $directory_input, $limit, $tag);
                return $result;
            }
        }

        return [
            'success' => 0,
            'message' => 'Post not found',
            "total" => 0
        ];
    }

    public function scrapeAnonymousBlog($domain, $tagged, $offset = 0, $count = 50)
    {
        $tagged = (isset($tagged) && $tagged != '') ? $tagged : false;
        $api = 'https://' . $domain . '.tumblr.com/api/read/json?debug=1&num=' . $count . ($tagged ? '&tagged=' . urlencode($tagged) : '');
        $endpoint = $api . '&start=' . ($count * $offset);
        $json = json_decode(file_get_contents($endpoint), true);
        return $json;

    }


    public function getContentFromUrl($link)
    {
        $request = $link;
        $useragent = $this->userAgent();
        $ch = curl_init($request);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function userAgent()
    {
        $useragent[] = "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.2 Safari/537.36";
        $useragent[] = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1468.0 Safari/537.36";
        $useragent[] = "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1467.0 Safari/537.36";

        return $useragent[array_rand($useragent)];
    }

    public function handleImagesDataInPost($data, $directory_input, $limit, $tag)
    {
        $folder_name = createFolder();
        $images = [];
        $count_image = 1;
        $tag_slug = str_slug($tag);
        $i = 1;
        if (count($posts = json_decode(json_encode($data), true))) {
            foreach ($posts as $post) {
                if (isset($post['photos'])) {
                    foreach ($post['photos'] as $photo) {
                        if ($count_image <= $limit) {
                            $count_image++;
                            $image_url = $photo['original_size']['url'];
                            /*
                             * move image to the temple folder
                             * */
                            $type = explode('.', $image_url)[count(explode('.', $image_url)) - 1];
                            $image_name = $tag_slug . date('-Y-m-d-', time()) . $i . '.' . $type;

                            $i++;
                            handleSaveImage($folder_name, $image_url, $image_name);
                            $images[] = [
                                'image_url' => $image_url,
                                'image_name' => $image_name,
                                'caption' => cleanSpecialChar($post['summary'])
                            ];
                        }
                    }
                }

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
                'zip_link' => url('uploads/' . $folder_name . '.zip'),
                'post_type' => 'photo'
            ];


        }
        return [
            'success' => 0,
            "total" => 0,
            'message' => 'Post not found'
        ];


    }
}
