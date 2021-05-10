<?php

namespace LTN\Utils;

use DateTime;

class Logger
{
    const SPACING = 40;
    const FOLDER_NAME = 'logs';

    public static function save($message)
    {
        $path = ROOT_DIR . DIRECTORY_SEPARATOR . self::FOLDER_NAME;

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $filename = new DateTime();
        $filename = $filename->format('m_d_Y');

        $filePath = $path . DIRECTORY_SEPARATOR . $filename;

        if (is_array($message)) {
            $message = array_reduce($message, function ($carry, $currMessage) {
                $carry = $carry . str_pad($currMessage, self::SPACING);
                return $carry;
            });
        }

        file_put_contents($filePath, $message . PHP_EOL, FILE_APPEND);
    }
}
