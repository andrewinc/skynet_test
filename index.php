<?php
/**
 * Скрпит реализуюзий API-запросы типа
 * `GET /users/{user_id}/services/{service_id}/tarifs`
 * `PUT /users/{user_id}/services/{service_id}/tarif`
 * и легко расширяется на другие виды запросов.
 * Выводит в ответ JSON согласно условиям задачи
 * @see https://sknt.ru/job/backend/
 */
include 'db_cfg.php';

/**
 * Установить HTTP-код ответа и вернуть простой объект для превращения его в json
 * @param int $httpCode Опционально. Код ответа вэб-сервера, который вернуть после HTTP-запроса. (по умолчанию 404)
 * @param string $resultText Опционально. Содержимое поля result возвращаемого объекта (по умолчанию 'error')
 * @return stdClass с полем result
 */
function returnObject($httpCode = 404, $resultText = 'error') {
    http_response_code($httpCode);
    return (object) array('result' => $resultText);
}

// Ассоциативный массив c uri-паттернами.
// Если успешен preg_match URI к этому паттерну, то вызывается функция которой паттерн соответствует,
// с параметрами что "сматчились" в регулянрном выражении
$routing =array(
    'GET' => array(
        "/^\/users\/([0-9]*)\/services\/([0-9]*)\/tarifs$/" => function($all, $user_id, $service_id) {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if (mysqli_connect_errno()) {
                return returnObject(500, mysqli_connect_error());
            }
            try {
                $result = array();

                $st = $mysqli->prepare(
                    "SELECT t.title, t.link, t.speed, t.tarif_group_id " .
                    "FROM services as s LEFT JOIN tarifs as t ON (t.ID = s.tarif_id) " .
                    "WHERE s.ID=?"
                );
                try{
                    if ($st->bind_param('i', $service_id) && $st->execute()) {
                        $st->bind_result($title, $link, $speed, $tarif_group_id);
                        if ($st->fetch()) {
                            $result['title'] = $title;
                            $result['link'] = $link;
                            $result['speed'] = intval($speed);
                            $result['tarifs'] = array();
                        }
                    }
                } finally {
                    $st->close();

                    if (isset($result['tarifs'])) {
                        $res = $mysqli->query(
                            sprintf(
                                "SELECT " .
                                    "ID, " .
                                    "title, " .
                                    "price, " .
                                    "pay_period, " .
                                    "CONCAT(unix_timestamp(CURDATE() + interval pay_period month), '%s') as new_payday, " .
                                    "speed " .
                                "FROM tarifs " .
                                "WHERE tarif_group_id = %d",
                                date('O'),
                                $tarif_group_id
                            )
                        );

                        if ($res) {
                            while ($row = $res->fetch_assoc()) {
                                $result['tarifs'][] = $row;
                            }
                            $res->close();
                        }
                    }
                }
                http_response_code(200);
                return (object) $result;
            } finally {
                $mysqli->close();
            }
        },
    ),

    'PUT' => array(
        "/^\/users\/([0-9]*)\/services\/([0-9]*)\/tarif$/" => function($all, $user_id, $service_id) {
            $putText = file_get_contents("php://input");
            $json=json_decode($putText, true);
            if (!is_null($json) && isset($json['tarif_id']) && is_numeric($json['tarif_id'])) {
                $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
                if (mysqli_connect_errno()) return returnObject(500, mysqli_connect_error());
                try{
                    $stmt = $mysqli->prepare("UPDATE services SET tarif_id = ?, payday = CURDATE() WHERE ID = ?");
                    try{
                        if ($stmt->bind_param('ii', $json['tarif_id'], $service_id) && $stmt->execute()) {
                            return (object) array('result' => 'ok');
                        } else {
                            return returnObject(520);
                        }
                    } finally {
                        $stmt->close();
                    }
                } finally {
                    $mysqli->close();
                }
            } else {
                return returnObject(400);
            }
        },
    )
);

// собственно код
$uri = $_SERVER['REQUEST_URI'];
$result = false;
if (isset($routing[$_SERVER['REQUEST_METHOD']]) && is_array($uriHandlers = $routing[$_SERVER['REQUEST_METHOD']])) {
    foreach($uriHandlers as $template => $callback) {
        if(preg_match($template, $uri, $m)) {
            if ($result = call_user_func_array($callback, $m)) {
                break;
            }
        }
    }
    if (!$result) {
        $result = returnObject(404, 'not defined');
    }
}

// Вывод результатов
header('Content-type: application/json; Charset=utf8');

/**
 * Преобразует escape-последовательности соответствующие русским символам в обычные символы.
 * Может понадобиться для более наглядного вывода результатов функции json_encode
 * @see http://javascript.ru/forum/295090-post12.html
 * ```
 * ruString('\u0417\u0435\u043c\u043b\u044f'); // 'Земля'
 * ruString('{"tarif":"\u0412\u043e\u0434\u0430"}'); // '{"tarif":"Вода"}'
 * ```
 * @param string $str Строка, содержащая escape-последовательности.
 * @return string
 */
function ruString($str){
    //новая реализация http://javascript.ru/forum/295090-post12.html
    $arr_replace_utf = array('\u0410', '\u0430','\u0411','\u0431','\u0412','\u0432',            //0
            '\u0413','\u0433','\u0414','\u0434','\u0415','\u0435','\u0401','\u0451','\u0416',   //1
            '\u0436','\u0417','\u0437','\u0418','\u0438','\u0419','\u0439','\u041a','\u043a',   //2
            '\u041b','\u043b','\u041c','\u043c','\u041d','\u043d','\u041e','\u043e','\u041f',   //3
            '\u043f','\u0420','\u0440','\u0421','\u0441','\u0422','\u0442','\u0423','\u0443',   //4
            '\u0424','\u0444','\u0425','\u0445','\u0426','\u0446','\u0427','\u0447','\u0428',   //5
            '\u0448','\u0429','\u0449','\u042a','\u044a','\u042b','\u044b','\u042c','\u044c',   //6
            '\u042d','\u044d','\u042e','\u044e','\u042f','\u044f');                             //7
    $arr_replace_cyr = array('А', 'а', 'Б', 'б', 'В', 'в',          //0
                   'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ё', 'ё', 'Ж',     //1
                   'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к',     //2
                   'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П',     //3
                   'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у',     //4
                   'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш',     //5
                   'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ы', 'ы', 'Ь', 'ь',     //6
                   'Э', 'э', 'Ю', 'ю', 'Я', 'я');                   //7
    return str_replace($arr_replace_utf,$arr_replace_cyr,$str);
}

echo ruString(json_encode($result));
echo "\n";