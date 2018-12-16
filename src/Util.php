<?php
namespace Gone\AppCore;

class Util
{
    /**
     * Get all files from given path recursively.
     *
     * @param string $path Path of directory
     *
     * @return array List of all files
     */
    public static function getRecursiveFilesFromDir($path)
    {
        $files             = [];
        $DirectoryIterator = new RecursiveDirectoryIterator($path);
        $IteratorIterator  = new RecursiveIteratorIterator($DirectoryIterator, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($IteratorIterator as $file) {
            $path = $file->getRealPath();
            if ($file->isFile() && substr($file->getFilename(), 0, 1) !== '.') {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Get all items from directory at parent level excluding hidden files.
     *
     * @param string $path Folder path
     *
     * @return array All items in parent directory.
     */
    public static function getItemsInDir($path)
    {
        if ($handle = opendir($path)) {
            $items = [];
            while (false !== ($entry = readdir($handle))) {
                //Skip any hidden files
                if (substr($entry, 0, 1) !== '.') {
                    $items[$entry] = $path . '/' . $entry;
                }
            }

            closedir($handle);
        }

        return $items;
    }

    /**
     * Auto build nested arrays.
     * Pass an array of keys and one value to build a multidimensional array.
     * e.g: Build an array using keys: Vehicle, Type, Model and Colour with value of Yellow.
     * Result:
     * array(
     *     'Vehicle' => array(
     *         'Type' => array(
     *             'Model' => array(
     *                 'Colour' => 'Yellow'
     *             )
     *         )
     *     )
     * ).
     *
     * @param array  $keys Array of keys to build
     * @param string $val  Value to add to the last nested array
     *
     * @return array Multidimensional array
     */
    public static function autoBuildMultidimensionalArr(array $keys, $val)
    {
        if (count($keys) == 0) {
            return $val;
        }

        return [$keys[0] => self::autoBuildMultidimensionalArr(array_slice($keys, 1), $val)];
    }

    /**
     * Companion function to match parse_url.
     *
     * @param $parsed_url
     *
     * @return string
     */
    public static function unparse_url($parsed_url)
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}
