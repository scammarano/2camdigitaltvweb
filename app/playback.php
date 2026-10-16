<?php
/** Digital Signage active schedule selection. Calendar uses the physical site's timezone. */
declare(strict_types=1);
function playback_schedule_matches(array $schedule, DateTimeImmutable $now):bool {
    $day=(int)$now->format('N')-1; // Monday=0
    if((((int)$schedule['days_mask']) & (1 << $day))===0)return false;
    $date=$now->format('Y-m-d');
    if(!empty($schedule['valid_from'])&&$date<$schedule['valid_from'])return false;
    if(!empty($schedule['valid_until'])&&$date>$schedule['valid_until'])return false;
    $time=$now->format('H:i:s');
    return $time>=(string)$schedule['start_time'] && $time<(string)$schedule['end_time'];
}
function playback_site_timezone(?string $timezone):DateTimeZone {
    try{return new DateTimeZone($timezone ?: 'UTC');}
    catch(Throwable $e){return new DateTimeZone('UTC');}
}
