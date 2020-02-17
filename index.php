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
        '/^\/$/' =>function($all){
            http_response_code(200);
            header('Content-type: text/html; Charset=utf8');
            $port=null;
            $root = isset($_SERVER['SERVER_PORT'])?( (443==($port=$_SERVER['SERVER_PORT']))?'https':'http'):'http';
            $root.= "://";
            $root.= isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:$_SERVER['SERVER_ADDR'];//'127.0.0.1'
            if (!is_null($port) && !in_array($port, array(80,443))) {
                $root.=":{$port}";
            }
            echo str_replace("%root%", $root, file_get_contents("page.index.php"));
            exit();
        }        
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

echo json_encode($result, JSON_UNESCAPED_UNICODE);
