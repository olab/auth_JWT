<?php

$observers = [
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => 'olab_local_course_section_updated_event_listener',
        'includefile' => null,
        'internal' => true,
        'priority' => 9999,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => 'olab_local_course_module_updated_event_listener',
        'includefile' => null,
        'internal' => true,
        'priority' => 9999,
    ],
];
