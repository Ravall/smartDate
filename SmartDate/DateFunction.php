<?php
/**********************************************************************************************
    smart_date_function                 v0.4
    author                              Alexey Klimenko aka greyjedi
    date                                12.04.2011

    Формат вызова
    smart_date_function($formula,$year);
    INPUT
        $formula - Формула по которой будет выполнен поиск дат (обязательный)
        $year    - Год, для которого будет выполнен поиск, по умолчанию - текущий. (необязательный)
    OUTPUT
        array    - Массив, каждый элемент которого также массив из трёх элементов
                   [0] - день, [1] - месяц, [2] - год.
                   Массив отсортирован по возрастанию.

----------------------------------------------------------------------------------------------

    Формула $formula состоит из 3-х полей отделённых друг от друга символом "|".
    [поле дат|фильтр дня недели|фильтр данных]

    1. Поле дат.
        1.1 Даты задаются в формате ДД.ММ.ГГГГ. Если поле ГГГГ опущено, то считается, что
            оно равно $year.
        1.2 Даты задаются в виде перечисления или интервала.
            1.2.1 Перечисление - несколько дат, с запятой между ними.
                  12.03.2011,14.04.2010,17.05
            1.2.2 Интервал - задаётся в виде "начальная дата"~"конечная дата".
                  12.04.2001~22.04.2001
            1.2.3 Возможна комбинация из перечисления и интервала.
                  21.10,22.11~5.12,15.12
        1.3 В поле дат может стоять формула. В этом случае сначала вычисляется она, затем
            результат её вычисления подставляется в исходную формулу. Уровень вложенности
            формул неограничен.
            [12.03,[14.01~14.02|1110001|-1,-2]|1110001|1,2,3]
        1.4 Вместо даты или интервала можно подставить функцию. Встроенная функция
            оформляется внутри фигурных скобок. В качестве аргумента функции может
            указываться год, для готорого она вызывается. Если год не указан, то
            тогда он считается равным $year. Регистр не важен.
            [{B}~01.02||]=[01.01~01.02]
            [{be(1927)}||]=[01.01.1927~31.12.1927||]
            [{Pascha(2012)}||] - Православная прасха в 2012 году.
        1.5 Смещение дат
            1.5.1 Смещение года. Если в дате год указан со знаком "+" или "-" то он интерпретируется
                  не как год, а как смещение относительно года $year.
                  13.10.-10 = 13.10.2001 ($year=2011)
                  21.12.+7  = 21.12.2018 ($year=2011)
            1.5.2 Смещение дней. Если дата начинается с "число>" или "число<" то такая дата сдвигается
                  вперед по времени (">") или назад по времени ("<") на "число" дней. "Число" может
                  быть отрицательным.
                  12>29.12.2001 = -12<29.12.2001 = 10.01.2002
                  2<1.11.1876 = -2>1.11.1876 = 30.10.1876

    2. Фильтр дня недели
       Фильтр может состоять из 7-ми символов "0" и "1" или быть пустым. Если фильтр не задан,
       то считается, что он равен "1111111". Фильтр указывает какие дни недели нужно оставить
       в выборке дат. Символы идут в порядке - "пн", "вт", - , "вс".
       0110000 - оставить в выборке только вторники и среды.

    3. Фильтр данных
       Фильтр определяет какие данные оставить, в зависимости от того, на каком месте в выборке
       они находятся. "0" - оставить всё. Отрицательные числа определяют какие данные оставить с
       конца выборки.
       1,-1 - оставить первую и последнюю дату.
       0,1,3 - оставить все даты.

    Примеры формул:
        [{BE(2001)},[{BE}||]|1000000|1,-1] - все понедельники за текущий и 2001 год
        [{BE(2001)},{BE()}|1000000|1,-1] - аналогично




**********************************************************************************************/
namespace SmartDate;

//Для скобки на позиции $start находим закрывающую скобку
function findCloseBracket($str, $start=0)
{
    if(!is_numeric($start)) return false;
    $len = strlen($str);
    if($start>$len-2) return false;
    switch($str[$start])
    {
        case '{': $cb = '}'; break;
        case '(': $cb = ')'; break;
        case '[': $cb = ']'; break;
        default: return false;
    }
    $n = 1;

    for($i=$start+1; $i<$len; ++$i)
    {
        if($str[$i]==$cb)
        {
            $n--;
            if($n==0) return $i;
        } else if($str[$i]==$str[$start])
        {
            $n++;
        }
    }
    return false;
}

//echo findCloseBracket('2t8u1g[398438(ripo(34839)(34342{33}{34323{}))]',13);

/*
Проверяем год на високосность
*/
function isLeapYear($y)
{
	return (($y%4==0 && $y%100!=0) || $y%400==0) ? 1 : 0;
}

/*
Проверяем дату на корректность
*/
function isDateCorrect($d,$m,$y="")
{
    if(!isset($y) || $y=="")
	{
		$dt = getdate();
		$y=$dt['year'];
	}

	if(!isset($d) || !isset($m) || !ctype_digit((string)$y)) return 0;
	if(!ctype_digit((string)$d) || $d<1 || $d>31) return 0;
	if(!ctype_digit((string)$m) || $m<1 || $m>12) return 0;
	if($m==2 && $d>28+isLeapYear($y)) return 0;
	if(($m==4 || $m==6 || $m==9 || $m==11) && $d==31) return 0;
	return 1;
}



/*
Находим день недели. В отличии от getdate работает и после 2036 года.

Для нашего современного календаря:

W = d + [ (13m - 1) / 5 ] + y + [ y / 4 ] + [ c / 4 ] - 2c

где d - число месяца;
m - номер месяца, начиная с марта (март=1, апрель=2, ... февраль=12);
y - номер года в столетии (например, для 1965 года y=65. Для января и февраля 1965 года, т.е.
для m=11 или m=12 номер года надо брать предыдущий, т.е. y=64);
c - количество столетий (например, для 2000 года c=20. И здесь для января и февраля 2000 года
надо брать предыдущее столетие с=19);
квадратные скобки означают целую часть полученного числа (отбрасываем дробную).

Результат W делите на 7 и модуль остатка от деления даст день недели (воскресенье=0, понедельник=1, ... суббота=6)

Пример: для 31 декабря 2008 года определяем:
d=31, m=10, y=8, c=20

По формуле находим:
W = 31 + [ ( 13 * 10 - 1 ) / 5 ] + 8 + [ 8 / 4 ] + [ 20 / 4 ] - 2 * 20 =
= 31 + 25 + 8 + 2 + 5 - 40 = 31

Теперь делим W на 7 и находим остаток от деления: 31 / 7 = 4 и 3 в остатке.
Тройка соответствует дню недели СРЕДА.
*/
function getDayOfWeek($d,$m,$y)
{
	if(!isDateCorrect($d,$m,$y)) return -1;
	if($m<3) $y--;
	$c = (int)($y/100);
	$y %= 100;
	$m -= 2; if($m<1) $m += 12;
	$W = ($d + (int)((13*$m - 1)/5) + $y + (int)($y/4) + (int)($c/4) - 2*$c)%7;
	if($W<=0) $W+=7;
	return $W;
}
/*
//Проверка

echo getDayOfWeek(23,9,2045)."(6)\r\n"; //6 - суббота
echo getDayOfWeek(29,2,2048)."(6)\r\n"; //6 - суббота
echo getDayOfWeek(19,10,2099)."(1)\r\n\r\n"; //1 - понедельник

$n=0;
for ($i=0; $i<400; ++$i)
{
	$dt = rand()*65535;
	$dt = getdate($dt);
	$wd = getDayOfWeek($dt['mday'],$dt['mon'],$dt['year'])%7;
	echo $dt['mday'].'.'.$dt['mon'].'.'.$dt['year']." - ".$wd.'/'.$dt['wday'].(($wd==$dt['wday']) ? '   ' : '(-)');
	echo ($n%4==3) ? "\r\n" : "\t ";
	$n++;
}
*/

//Проверка корректности фильтра дня недели
function isWDayFilterCorrect($wdayfilter)
{
	if(strlen($wdayfilter)!=7)
		throw new Exception("Неверный фильтр дня недели! Неверная длина.");
	for($i=0; $i<7; ++$i)
		if($wdayfilter[$i]!='0' && $wdayfilter[$i]!='1')
			throw new Exception("Неверный фильтр дня недели! Неверный символ. <$wdayfilter>($wdayfilter[$i])");
}
//isWDayFilterCorrect("0190011");

//Проверка корректности фильтра данных
function isNumFilterCorrect($numfilter)
{
	$numfilter = trim($numfilter);
	$l = strlen($numfilter);
	if($numfilter[0]==',' || $numfilter[$l-1]==',')
		throw new Exception("Неверный фильтр данных! Запятые с краю!");
	for($i=0; $i<$l; ++$i)
	{
		if($numfilter[$i]!=' ' && $numfilter[$i]!=',' &&
           !ctype_digit($numfilter[$i]) && !$numfilter[$i]=='-' && !$numfilter[$i]='+')
			throw new Exception("Неверный фильтр данных! Неверный символ. <$numfilter>($numfilter[$i])");
	}

	$data = explode(',',$numfilter);
	foreach($data as $value)
	{
		if(!is_numeric($value))
			throw new Exception("Неверный фильтр данных!");
	}
}
//isNumFilterCorrect(' 13,15,,18,  11, ,12,13 ');
function numDaysInMonth($month,$year)
{
	switch($month)
	{
		case  1:
		case  3:
		case  5:
		case  7:
		case  8:
		case 10:
		case 12: return 31;
		case  4:
		case  6:
		case  9:
		case 11: return 30;
		case  2: return 28+isLeapYear($year);
	}
	throw new Exception("Неверный месяц в numDaysInMonth!");
}

//Сдвигает дату на $shift дней
function dateShift($day,$month,$year,$shift)
{
    if($shift==0) return array($day,$month,$year);
	$day += $shift;
	while($day<1)
	{
		--$month;
		if($month<1)
		{
			$month=12;
			--$year;
		}
		$day+=numDaysInMonth($month,$year);
	}

	while($day>numDaysInMonth($month,$year))
	{
		$day-=numDaysInMonth($month,$year);
        ++$month;
		if($month>12)
		{
			$month=1;
			++$year;
		}
	}
	return array($day,$month,$year);
}

function dateShiftStr($day,$month,$year)
{
    return implode('.',dateShift($day,$month,$year));
}

//echo dateShift(31,12,2010,128)."<br>";
//echo dateShift(31,12,2010,-365)."<br>";

//Сравниваются даты
//  1 - вторая дата позже
// -1 - первая дата позже
//  0 - даты совпадают
function dateCompare($d1,$m1,$y1,$d2,$m2,$y2)
{
    if((int)$y2>(int)$y1) return  1;
    if((int)$y2<(int)$y1) return -1;
    if((int)$m2>(int)$m1) return  1;
    if((int)$m2<(int)$m1) return -1;
    if((int)$d2>(int)$d1) return  1;
    if((int)$d2<(int)$d1) return -1;
    return 0;
}

//Вычисляем дату Пасхи
function Pascha($year)
{
    //Православная
	$a = $year % 4;
	$b = $year % 7;
	$c = $year % 19;
	$d = ( 19 * $c + 15 ) % 30;
	$e = ( 2 * $a + 4 * $b - $d + 34 ) % 7;
	$month = 3 + (int)(($d + $e + 21) / 31);
	$day = ( $d + $e + 21 ) % 31 + 1;

    //Переход на григорианский календарь в России состаялся 26.01.1918г.
    //Ввод григорианского календаря в католических странах 5.10.1582г.
    //Вот тут надо подумать, какую дату ставить в сравнение?
    //Дата более ранняя - выдаём дату по юлианскому календарю
	if(dateCompare($day,$month,$year,5,10,1582)>=0) return $day.'.'.$month.'.'.$year;

    //Если дата более поздняя - пересчитываем на григорианский календарь
    list($day,$month,$year) = dateShift($day,$month,$year,(int)($year/100)-(int)($c/4)-2);
    //Ближайшее раннее воскресенье
    $a = getDayOfWeek($day,$month,$year);
    if($a<7) list($day,$month,$year) = dateShift($day,$month,$year,-$a);
    return $day.'.'.$month.'.'.$year;


	/* Католическая (Врет для дат, ранее 5.10.1582 - даты введения Григорианского календаря)
	$century = (int)($year/100);
	$G = $year % 19;
	$K = (int)(($century - 17)/25);
	$I = ($century - (int)($century/4) - (int)(($century - $K)/3) + 19*$G + 15) % 30;
	$I = $I - (int)($I/28)*(1 - ((int)($I/28))*((int)(29/($I + 1)))*((int)((21 - $G)/11)));
	$J = ($year + (int)($year/4) + $I + 2 - $century + (int)($century/4)) % 7;
	$L = $I - $J;
	$Month = 3 + (int)(($L + 40)/44);
	$Day = $L + 28 - 31*((int)($Month/4));
	return $Day.'.'.$Month.'.'.$year;*/
}
/*
echo Pascha(2001).' - 15.04.2001<br>';
echo Pascha(2002).' - 05.05.2002<br>';
echo Pascha(2003).' - 27.04.2003<br>';
echo Pascha(2004).' - 11.04.2004<br>';
echo Pascha(2005).' - 01.05.2005<br>';
echo Pascha(2006).' - 23.04.2006<br>';
echo Pascha(2007).' - 08.04.2007<br>';
echo Pascha(2008).' - 27.04.2008<br>';
echo Pascha(2009).' - 19.04.2009<br>';
echo Pascha(2010).' - 04.04.2010<br>';
echo Pascha(2011).' - 24.04.2011<br>';
echo Pascha(2012).' - 15.04.2012<br>';
echo Pascha(2013).' - 05.05.2013<br>';
echo Pascha(2014).' - 20.04.2014<br>';
echo Pascha(2015).' - 12.04.2015<br>';
echo Pascha(2016).' - 01.05.2016<br>';
echo Pascha(2017).' - 16.04.2017<br>';
echo Pascha(2018).' - 08.04.2018<br>';
echo Pascha(2019).' - 28.04.2019<br>';
echo Pascha(2020).' - 19.04.2020<br>';
/*/

//Вычисляем функцию даты. Функция может быть задана в виде
//func или func() - вычисляем её для года $year
//func(YEAR) - вычисляем её для года YEAR
//Регистр не важен.
//Например
//B - возвращает 01.01.$year
//E() - возвращает 31.12.$year
//
function dateFunc($func,$year)
{
	$func = trim($func);
	$pos = strpos($func,'(');
	if($pos && $pos==0) throw new Exception("Ошибка функции даты! Нет названия функции $func.");
	$param ="";
	if($pos>0)
	{
		$pos2 = strpos($func,')');
		if(!$pos2) throw new Exception('Ошибка функции даты! Непарные скобки.');
		if($pos2!=strlen($func)-1) throw new Exception("Ошибка функции даты!");
		$year = trim(substr($func,$pos+1,$pos2-$pos-1));
	}
	if(!is_numeric($year) ) throw new Exception("Ошибка функции даты! Неверный год. $func($year)");
	if((float)$year!=(int)$year) throw new Exception("Ошибка функции даты! Неверный год. $func($year)");

	$func = ($pos>0) ? strtoupper(trim(substr($func,0,$pos))) : strtoupper(trim($func));
	switch($func)
	{
		case "B": 	    return "01.01.$year";
		case "E":		return "31.12.$year";
		case "BE":	    return "01.01.$year~31.12.$year";
		case "PASCHA":	return Pascha($year);
	}
	throw new Exception("Ошибка функции даты! Неизвестная функция $func.");
}
//echo dateFunc(" E ( -1235.5 ) ",2011);

function simpleDate($date,$y)
{
    $date = trim($date);
    $date = explode('.',$date);
    if(!isset($date[0]) || !isset($date[1])) throw new Exception("Неверная дата!");

    $day = $date[0];
    $month = $date[1];
    $year = (isset($date[2])) ? $date[2] : $y;

    //Проверяем усть ли сдвиг даты
    $shift = false;
    $pos1 = strpos($day,'>');
    $pos2 = strpos($day,'<');
    if($pos1 || $pos2)
    {
        $dir = ($pos1) ? 1 : -1;
        $pos = ($dir==1) ? $pos1 : $pos2;
        $val = (int)substr($day,0,$pos);
        $shift = $dir * $val;
        $day = substr($day,$pos+1);
    }

    if(!isset($year) || $year=="") $year=$y;
    if( $month=="" || !ctype_digit((string)$month) ||
        $day=="" || !ctype_digit((string)$day))
        throw new Exception("Неверная дата!");
    if($year[0]=='-' || $year[0]=='+')
    { //Если год в формате +х или -х - то это не год, а смещение
        $year = $y + $year;
    }
    if($shift) list($day,$month,$year) = dateShift($day,$month,$year,$shift);
    if(isDateCorrect($day,$month,$year))  return array($day,$month,$year);
    throw new Exception("Неверная дата!");
}

//print_r(simpleData("12.07.2003",2011));

//Применяем фильтр дней недели
function filterWDays($days,$wdayfilter)
{
    if($wdayfilter=='1111111') return $days;
    if($wdayfilter=='0000000') return array();
    for($i=count($days)-1; $i>=0; --$i)
    {
        if($wdayfilter[getDayOfWeek($days[$i][0],$days[$i][1],$days[$i][2])-1]=='0')
        {
            unset($days[$i]);
        }
    }
    return $days;
}

//Применяем фильтр номера
function filterNum($Days,$numfilter)
{
    $filternums = explode(',',$numfilter);
    //Проверяем, может и не надо фильтровать?
    foreach($filternums as $filter) if($filter=='0') return $Days;
    //Копируем
    foreach($Days as $day) $days[]=$day;
    //Фильтруем
    $size = count($days);
    foreach($filternums as $filter)
    {
        if($filter>0) $days[$filter-1]['mark']=1;
        else $days[$size+$filter]['mark']=1;
    }
    //Удаляем ненужное
    for($i=0; $i<$size; ++$i)
    {
        if(!isset($days[$i]['mark'])) unset($days[$i]);
        else unset($days[$i]['mark']);
    }
    return $days;
}

//Быстрая сортировка для массива, каждый элеиент которого
//также массив со значениями [0]-день [1]-месяц [2]-год
function quicksort( $arr, $l = 0 , $r = NULL ) {
    static $list = array();
    if( $r == NULL )
        $list = $arr;

    if( $r == NULL )
        $r = count($list)-1;

    $i = $l;
    $j = $r;

    $tmp = $list[(int)( ($l+$r)/2 )];
    do {
        while(dateCompare($list[$i][0],$list[$i][1],$list[$i][2],$tmp[0],$tmp[1],$tmp[2])>0) $i++;
        while(dateCompare($tmp[0],$tmp[1],$tmp[2],$list[$j][0],$list[$j][1],$list[$j][2])>0) $j--;
        if( $i <= $j ) {
            $w = $list[$i];
            $list[$i] = $list[$j];
            $list[$j] = $w;
            $i++;
            $j--;
        }
    }while( $i <= $j );
    if( $l < $j )
        quicksort(NULL, $l, $j);
    if( $i < $r )
        quicksort(NULL, $i, $r);

    return $list;
}

function dateFormula($formula,$year)
{
    while(is_numeric($pos=strpos($formula,"["))) //Нашли открывающую скобку формулы
    {
        $pos2 = findCloseBracket($formula,$pos);
        if(!$pos2) throw new Exception("Ошибка в формуле! Не парные скобки.");
        //Формула в скобках
        $newformula = substr($formula,$pos+1,$pos2-$pos-1);
        //Обработка формулы из скобок
        $result = smart_date_function($newformula,$year);
        for($i=0; $i<count($result); ++$i) $result[$i] = implode('.',$result[$i]);
        $result = implode(',',$result);
        //Замена формулы результатом
        $tmp = substr($formula,0,$pos);
        $formula = $tmp.$result.substr($formula,$pos2+1);

    }
    return $formula;
}

//Получаем поля формулы
function getFields($formula)
{
    $fields = explode("|",$formula);

    if(count($fields)==0) throw new Exception("Пустая формула!");
    if($fields[0]=='') throw new Exception('Пустая дата в формуле!');
    if(count($fields)==1) $fields[]='1111111';
    if(count($fields)==2) $fields[]='0';
    if(count($fields)>3) throw new Exception('Ошибка в формуле! Слишком много фильтров.');
    if($fields[1]=='') $fields[1]='1111111';
    if($fields[2]=='') $fields[2]='0';

    return $fields;
}


function sortAndOrder($dateArray)
{
    if(count($dateArray)==0) return $dateArray;

    //Все значения переводим в числовой формат и c правильной нумерацией индексов массива
    foreach($dateArray as $day) $res[]=array((int)$day[0],(int)$day[1],(int)$day[2]);

    //Выводим отсортированый массив
    $sorted = true;
    for($i=1; $i<count($res); ++$i)
        if(dateCompare($res[$i-1][0],$res[$i-1][1],$res[$i-1][2],
                       $res[$i][0],$res[$i][1],$res[$i][2])<0)
        {
            $sorted = false;
            break;
        }
    return ($sorted) ? $res : quicksort($res);
}

function smart_date_function($formula,$year='')
{
    if(!isset($year) || $year=="")
    {
        $dt = getdate();
        $year=$dt['year'];  //Год по умолчанию - текущий
    }
	//[даты|фильтр1|фильтр2]
	$formula = trim($formula);
	$l = strlen($formula);
    if($l==0) return array();

	if($formula[0]=='[' && findCloseBracket($formula,0)==$l-1)	$formula = substr($formula,1,$l-2);

	//Находим все формулы в поле дат, вычисляем их и замещаем значениями
    $formula = dateFormula($formula,$year);

	//Получаем поля формулы
    list($dates,$wdayfilter,$numfilter) = getFields($formula);

	//Проверка фильтров
	//Проверка фильтр дня недели
	isWDayFilterCorrect($wdayfilter);
	//Проверка фильтра полученных данных
	isNumFilterCorrect($numfilter);

	//Ищем встроенные функции в датах и заменяем их результатом
	while(is_numeric($pos = strpos($dates,'{')))
	{
		$pos2 = findCloseBracket($dates,$pos);
		if(!$pos2) throw new Exception("Нет закрывающей скобки функции даты!");
		//Формула даты
		$func = substr($dates,$pos+1,$pos2-$pos-1);
		//Результат
		$result = dateFunc($func,$year);
		//Заменяем формулу результатом
		$tmp = substr($dates,0,$pos);
		$dates = $tmp.$result.substr($dates,$pos2+1);
	}

    //$dates приведена к виду "дата", "дата1"~"дата2"
    $dates = explode(",",$dates);
    foreach($dates as $date)
    {
        if(strpos($date,"~")) //Интервал
        {
            list($date1,$date2)=explode('~',$date);
            $date1 = simpleDate($date1,$year);
            $date2 = simpleDate($date2,$year);
            while(dateCompare($date1[0],$date1[1],$date1[2],$date2[0],$date2[1],$date2[2])>=0)
            {
                $days[]=$date1;
                $date1 = dateShift($date1[0],$date1[1],$date1[2],1);
            }
        } else //Простая дата
        {
            $days[]=simpleDate($date,$year);
        }
    }


    //Применить фильтр дней недели
    $days = filterWDays($days,$wdayfilter);

    $days = sortAndOrder($days);

    //Применить фильтр порядковый
    $days = filterNum($days,$numfilter);

    return sortAndOrder($days);
}

class DateFunction
{
    static function run($formula, $year='') {
        return smart_date_function($formula, $year);
    }
}
