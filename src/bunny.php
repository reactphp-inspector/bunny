<?php

namespace Bunny;

use ReactInspector\Stream\Bridge;

function fread($handle, $length)
{
    $data = \fread($handle, $length);
    Bridge::incr('read', (float)strlen($data));
    return $data;
}

function fwrite($handle, $data)
{
    $writtenLength = \fwrite($handle, $data);
    Bridge::incr('write', (float)$writtenLength);
    return $writtenLength;
}
