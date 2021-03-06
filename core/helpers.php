<?php

use Data\Json;

/**
 * Fetches a value from a config file
 * @param string $path The name of the file and the path to the desired value,
 * all separated by dots (.)
 * @param mixed $default The default value if the path does not resolve
 * @return mixed
 */
function config($path, $default = null) {
    $path = explode('.', $path);
    $filename = array_shift($path);
    $data = include ROOT . 'config' . DIRECTORY_SEPARATOR . $filename . '.php';
    if (count($path)) {
        foreach ($path as $arg) {
            if (!array_key_exists($arg, $data)) return $default;
            $data = $data[$arg];
        }
    }
    return $data;
}

/**
 * Changes a string from snake_case to CamelCase
 * @param string $str
 * @return string
 */
function _toCamel($str) {
    if (!is_string($str)) return '';
    $func = create_function('$c', 'return strtoupper($c[1]);');
    return ucfirst(preg_replace_callback('/_([a-z])/', $func, $str));
}

/**
 * Turns camelCasedString to under_scored_string
 * @param string $str
 * @return string
 */
function camelTo_($str) {
    if (!is_string($str) || empty($str)) return '';
    $str[0] = strtolower($str[0]);
    $func = create_function('$c', 'return "_" . strtolower($c[1]);');
    return preg_replace_callback('/([A-Z])/', $func, $str);
}

/**
 * Creates a global unique id
 * @return string
 */
function createGUID() {
    if (function_exists('com_create_guid')) {
        return substr(com_create_guid(), 1, 36);
    }
    else {
        mt_srand((double) microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);
        $uuid = substr($charid, 0, 8) . $hyphen .
                substr($charid, 8, 4) . $hyphen .
                substr($charid, 12, 4) . $hyphen .
                substr($charid, 16, 4) . $hyphen .
                substr($charid, 20, 12);

        return $uuid;
    }
}

/**
 * Fetches the first part from the given path string
 * @param string $path
 * @return string
 */
function getFirstPath(&$path) {
    if ($node = strstr($path, '/', true)) {
        $path = substr($path, strlen($node) + 1);
        return $node;
    }
    return;
}

/**
 * Make each value in the array an array
 * @param array $array
 * @return array
 */
function makeValuesArray(array &$array) {
    foreach ($array as &$value) {
        if (!is_array($value)) $value = array($value);
    }
    return $array;
}

/**
 * Fetches the HTTP Request method
 * @return string
 */
function requestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

const UPLOAD_ERROR_NO_FILE = 'No file found';
const UPLOAD_ERROR_SIZE = 'Size of file is too big';
const UPLOAD_ERROR_EXTENSION = 'File extension is not allowed';
const UPLOAD_ERROR_PATH = 'Create path failed';
const UPLOAD_ERROR_PERMISSION = 'Insufficient permission to save';
const UPLOAD_ERROR_FAILED = 'Upload failed';
const UPLOAD_SUCCESSFUL = 'File uploaded successfully';

/**
 * Uploads file(s) to the server
 * @param array $data Files to upload
 * @param array $options Keys include [(string) path, (int) maxSize, 
 * (array) extensions - in lower case, (array) ignore, (string) filename]
 * @return boolean|string
 */
function uploadFiles(array $data, array $options = array()) {
    $return = array('success' => array(), 'errors' => array());
    foreach ($data as $ppt => $info) {
        if (is_array($options['ignore']) && in_array($ppt, $options['ignore']))
                continue;
        makeValuesArray($info);

        foreach ($info['name'] as $key => $name) {
            if ($info['error'][$key] !== UPLOAD_ERR_OK) {
                $return['errors'][$ppt][$name] = UPLOAD_ERROR_NO_FILE;
                continue;
            }

            if (isset($options['maxSize'][$key]) && $info['size'] > $options['maxSize'][$key]) {
                $return['errors'][$ppt][$name] = UPLOAD_ERROR_SIZE;
                continue;
            }

            $tmpName = $info['tmp_name'][$key];
            $pInfo = pathinfo($name);
            if (isset($options['extensions']) && !in_array(strtolower($pInfo['extension']),
                                                                      $options['extensions'])) {
                $return['errors'][$ppt][$name] = UPLOAD_ERROR_EXTENSION;
                continue;
            }
            $dir = !empty($options['path']) ? $options['path'] : DATA . 'uploads';
            if (substr($dir, strlen($dir) - 1) !== DIRECTORY_SEPARATOR)
                    $dir .= DIRECTORY_SEPARATOR;
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    $return['errors'][$ppt][$name] = UPLOAD_ERROR_PATH;
                    continue;
                }
            }
            $filename = basename($pInfo['filename']) . '.' . $pInfo['extension'];
            if ($options['filename']) {
                $filename = $options['filename'];
                if ($key) $filename .= '_' . $key;
                if (!strstr($options['filename'], '.'))
                        $filename .= '.' . $pInfo['extension'];
            }
            $savePath = $dir . $filename;
            if (move_uploaded_file($tmpName, $savePath)) {
                $return['success'][$ppt][$key] = str_replace([ROOT, '\\'],
                                                             [HOST, '/'],
                                                             $savePath);
            }
            else {
                $return['errors'][$ppt][$key] = UPLOAD_ERROR_FAILED;
            }
        }
    }
    return $return;
}

/**
 * @param string $template Name of text/html template file to send
 * @param array $variables Array of variables to fill into the template file
 * @param string $plain Name of text/plain template file to send with the html
 * @return Email
 */
function email($template, array $variables = [], $plain = null) {
    $variables['APP_NAME'] = config('app.name');
    return new Email($template, $variables, $plain);
}

/**
 * 
 * @param string $filepath Path to template file with dot (.) used as directory
 * separator
 * @param array $variables
 * @param boolean $nl2br
 * @return string
 */
function template($filepath, array $variables = [], $nl2br = true) {
    if (!$content = file_get_contents(TEMPLATES . str_replace('.',
                                                              DIRECTORY_SEPARATOR,
                                                              $filepath) . '.html'))
            return null;
    if ($nl2br) $content = nl2br($content);
    if (count($variables)) {
        $vars = $vals = [];
        foreach ($variables as $key => $value) {
            $vars[] = '/{' . $key . '}/';
            $vals[] = $value;
        }
        return preg_replace($vars, $vals, $content);
    }
    return $content;
}

/**
 * Creates a token
 * @return string
 */
function createToken() {
    return md5(Json::createGUID());
}

/**
 * Converts a relative server url to absolute
 * @param string $relative_path
 * @return string
 */
function url($relative_path) {
    $url = config('app.urls.server');
    if (substr($url, strlen($url) - 1) !== '/') $url .= '/';
    if (substr($relative_path, 0, 1) == '/')
            $relative_path = substr($relative_path, 1);
    return $url . $relative_path;
}

/**
 * Converts a url to clickable link
 * @param string $url
 * @param string $label
 * @return string
 */
function aLink($url, $label = null) {
    return '<a href="' . $url . '">' . ($label ?: $url) . '</a>';
}
