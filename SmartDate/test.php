<?php
require __DIR__. '/DateFunction.php';
use SmartDate;
var_dump(SmartDate\smart_date_function('11.11~15.11'));
var_dump(SmartDate\DateFunction::run('11.11~15.11'));