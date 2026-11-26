<?php
namespace AfterShow;

/** Parse FPP's callbacks.php --type media --data JSON invocation. */
final class MediaCallback
{
    public static function dispatch(array $args, callable $stop)
    {
        $type=array_search('--type',$args,true);
        $data=array_search('--data',$args,true);
        if ($type===false || ($args[$type+1] ?? '')!=='media' || $data===false) return false;
        $raw=$args[$data+1] ?? '';
        if (!is_string($raw) || strlen($raw)>65536) throw new \InvalidArgumentException('Invalid FPP media callback payload.');
        $event=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        // Empty Media is FPP's media-close notification. A sequence without
        // media must never stop radio; metadata titles are not media names.
        if (!is_array($event) || !is_string($event['Media'] ?? null) || trim($event['Media'])==='') return false;
        return call_user_func($stop);
    }
}
