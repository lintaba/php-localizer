<?php
function _($str){echo $str;}
function __($str,$params...){echo sprintf($str,$params...);}
$a = 0;$b = 1;$c = 2;$d=[3];
echo "from".'multiple'."STRINGS";

echo "foo" . 4 . 'asd' . (2+2) . 'asdqwe' . ('foo' . 'bar');

echo "X".strtoupper("y");

echo "ASD";
echo "SELECT * FROM TABLE";
echo "";

echo _("foo");
echo __("foo");

echo "foo
bar
baz";

echo "'".'"';

echo '\''."\"";
//foo
echo "\'".'\"'."\\".'\\'."\' ' ";
//foo
echo "$a $b {$c} ${d[0]}";
//foo
echo "42% kesz";
//foo
echo "A".abs(0)."B";

echo "A".abs(abs(0) + 42 * 6)."B";
//foo
