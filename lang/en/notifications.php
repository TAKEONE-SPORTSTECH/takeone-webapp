<?php

// Notification titles, re-rendered at display time so they follow the
// recipient's locale (the stored title is only an English fallback).
// :actor is the person who triggered it (a name — never translated).
return [
    'follow'           => ':actor started following you',
    'post'             => ':actor shared a new post',
    'like'             => ':actor liked your post',
    'comment'          => ':actor commented on your post',
    'payment_approved' => 'Payment approved',
    'payment_refunded' => 'Payment refunded',
];
