<?php

function ft_is__premium() {
    return true;
}


if ( ft_is__premium() ){
    $array = array(
        'string' => 'this is pro string',
    );

    function get_content( ){
        // my pro content here
    }
} else {
    $array = array(
        'string' => 'this is free version',
    );

    function get_content( ){
        // my free content here
    }
}