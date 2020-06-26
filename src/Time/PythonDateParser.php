<?php
declare(strict_types=1);

namespace App\Time;


class PythonDateParser
{
    public static function createDateTimeImmutable($date): ?\DateTimeImmutable
    {
        $match = preg_match("/(\d+)\.(\d{0,6})\d*/", (string)$date, $matches);
        if ($match) {
            $timeMicros = $matches[1].'.'.$matches[2];
            return \DateTimeImmutable::createFromFormat('U.u', $timeMicros);
        }

        dump($date, $matches);

        return null;
    }
}
