<?php return function ($in) {
    $cx = Array(
        'flags' => Array(
            'jstrue' => true,
            'jsobj' => true,
        ),
        'scopes' => Array($in),
        'path' => Array(),

    );
    return '<h1>'.LCRun::enc('header', $cx, $in).'</h1>
'.LCRun::sec('notEmpty', $cx, $in, false, function($cx, $in) {return '
<ul>
'.LCRun::sec('item', $cx, $in, false, function($cx, $in) {return '
'.LCRun::sec('current', $cx, $in, false, function($cx, $in) {return '
    <li><strong>'.LCRun::enc('name', $cx, $in).'</strong></li>
';}).'
'.((LCRun::isec('current', $cx, $in)) ? '
    <li><a href="'.LCRun::enc('url', $cx, $in).'">'.LCRun::enc('name', $cx, $in).'</a></li>
' : '').'
';}).'
</ul>
';}).'
'.LCRun::sec('isEmpty', $cx, $in, false, function($cx, $in) {return '
<p>The list is empty.</p>
';}).'
';
}
?>