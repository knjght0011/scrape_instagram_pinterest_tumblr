<?php
use Maatwebsite\Excel\Facades\Excel;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 9/22/2017
 * Time: 2:17 PM
 */

function cleanString($text) {

    $text = strip_tags($text);

    $utf8 = array(
        '/[áàâãªä]/u'   =>   'a',
        '/[ÁÀÂÃÄ]/u'    =>   'A',
        '/[ÍÌÎÏ]/u'     =>   'I',
        '/[íìîï]/u'     =>   'i',
        '/[éèêë]/u'     =>   'e',
        '/[ÉÈÊË]/u'     =>   'E',
        '/[óòôõºö]/u'   =>   'o',
        '/[ÓÒÔÕÖ]/u'    =>   'O',
        '/[úùûü]/u'     =>   'u',
        '/[ÚÙÛÜ]/u'     =>   'U',
        '/ç/'           =>   'c',
        '/Ç/'           =>   'C',
        '/ñ/'           =>   'n',
        '/Ñ/'           =>   'N',
        '/–/'           =>   '-', // UTF-8 hyphen to "normal" hyphen
        '/[’‘‹›‚]/u'    =>   ' ', // Literally a single quote
        '/[“”«»„]/u'    =>   ' ', // Double quote
        '/ /'           =>   ' ', // nonbreaking space (equiv. to 0x160)
    );
    return preg_replace(array_keys($utf8), array_values($utf8), $text);
}
function handleSaveImage($folder, $image_url, $image_name)
{
    try {
        $img = file_get_contents($image_url);
        file_put_contents(public_path('uploads/' . $folder . '/' . $image_name), $img);
        return public_path('uploads/' . $folder . '/' . $image_name);
    } catch (Exception $ex) {
        return false;
    }
}

function createFolder()
{
    try {
        $tmp = uniqid();
        $dir = public_path('uploads/' . $tmp . '/');
        if (!file_exists($dir)) {
            if (mkdir($dir) === true) {
                return $tmp;
            }
        }
    } catch (\Exception $e) {
        die('cannot create folder tmp or missing uploads folder');
    }

}


function writeExcel($data, $folder, $directory_input)
{
    Excel::create('excel_image_path', function ($excel) use ($data, $directory_input) {
        // Set the title
        $excel->setTitle('Image Name');
        // Chain the setters
        $excel->setCreator('Ken')
            ->setCompany('Kan-tek');
        // Call them separately
        $excel->setDescription('This excel file will contain list of image after scrape');
        $excel->sheet('Sheetname1', function ($sheet) use ($data, $directory_input) {
            $sheet->appendRow(2, array(
                'DIRECTORY INPUT',
                ''
            ));
            $sheet->appendRow(4, array(
                $directory_input,
                ''
            ));
            $sheet->row(6, array(
                'CAPTION', 'DIRECTORY'
            ));
            foreach ($data as $k => $v) {
                $sheet->appendRow(array(
                    cleanString($v['caption']),
                    $directory_input . basename($v['image_name'])
                ));
            }
        });


    })->store('xls', public_path('uploads/' . $folder));
}

function zipData($source, $destination)
{
    if (extension_loaded('zip')) {
        if (file_exists($source)) {
            $zip = new ZipArchive();
            if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
                $source = realpath($source);
                if (is_dir($source)) {
                    $iterator = new RecursiveDirectoryIterator($source);
                    // skip dot files while iterating
                    $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($files as $file) {
                        $file = realpath($file);
                        if (is_dir($file)) {
                            $zip->addEmptyDir(str_replace($source, '', $file . '\\'));
                        } else if (is_file($file)) {
                            $zip->addFromString(trim(str_replace($source, '', $file), DIRECTORY_SEPARATOR), file_get_contents($file));
                        }
                    }
                } else if (is_file($source)) {
                    $zip->addFromString(basename($source), file_get_contents($source));
                }
            }
            return $zip->close();
        }
    }
    return false;
}

function delFolder($dir)
{
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? $this->delFolder("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function platformSlashes($path)
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function cleanSpecialChar($string)
{
    try {
//        $string = isset(explode('. ', $string)[0]) ? explode('. ', $string)[0] : $string;
        $string = str_replace('<p>', '', $string); // Replaces all spaces with hyphens.
        $string = str_replace('</p>', '', $string);
        $string = str_replace('<br/>', '. ', $string);
        $string = preg_replace('#<a(.*?)>(.*?)</a>#is', '', $string);
        $string = preg_replace('#<a(.*?)>(.*?)</a>#is', '', $string);
        $string = preg_replace('#http(.*?).com#is', '', $string);
        $string = preg_replace('#http(.*?)/#is', '', $string);
        $string = preg_replace('/[^A-Za-z0-9\ \.\'\&\@\,\(\)\/\'\|\:\-]/', '', $string); // Removes special chars.
        return $string;
    } catch (Exception $ex) {
        return $string;
    }
}