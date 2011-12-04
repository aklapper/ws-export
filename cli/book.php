#!/usr/bin/php
<?php

include('../utils/HttpException.php');
include('../utils/Api.php');
include('../book/Generator.php');
include('../book/Page.php');
include('../book/Book.php');
include('../book/BookProvider.php');


if($_SERVER['argc'] < 3 || $_SERVER['argc'] > 4) {
        if ($fp = fopen('help/book.txt', 'r')) {
                while(!feof($fp)) {
                        echo '   ' . fgets($fp, 4096);
                }
        }
} else {
        $lang = $_SERVER['argv'][1];
        $title = $_SERVER['argv'][2];
        $format = isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : 'epub';
                $path = isset($_SERVER['argv'][4]) ? $_SERVER['argv'][4] . '/' : '';
        try {
                $api = new Api($lang);
                $provider = new BookProvider($api);
                $data = $provider->get($title);
                if($format == 'epub-2' | $format == 'epub') {
                        include('../book/formats/Epub2Generator.php');
                        $generator = new Epub2Generator();
                } else {
                        throw new Exception('The file format is unknown');
                }
                $file = $generator->create($data);
                $path .= $title . '.' . $generator->getExtension();
                if($fp = fopen($path, 'w')) {
                        fputs($fp, $file);
                }
                echo "The ebook $path is created !\n";
        } catch(Exception $exception) {
                echo "Error: $exception\n";
        }
}