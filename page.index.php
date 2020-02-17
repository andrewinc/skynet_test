<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Справка по проекту</title>
    </head>
    <body>
        <h1>Тестовое задание для SkyNet</h1>
        <p>Задача реализовать REST-API</p>
        <p>Проверка вызовов</p>
        <ul>
            <li>GET на URL <strong>%root%/users/1/services/2/tarifs</strong> 
                результат - <a href="%root%/users/1/services/2/tarifs">JSON:</a>
            <code>{"title":"Вода","link":"http:\/\/www.sknt.ru\/tarifi_internet\/in\/2.htm","speed":100,"tarifs":[{"ID":"4","title":"Вода","price":"600.0000","pay_period":"3","new_payday":"1589662800+0300","speed":"100"},{"ID":"5","title":"Вода (3 мес)","price":"1650.0000","pay_period":"3","new_payday":"1589662800+0300","speed":"100"},{"ID":"6","title":"Вода (12 мес)","price":"5400.0000","pay_period":"3","new_payday":"1589662800+0300","speed":"100"}]}</code>
            </li>
            <li>PUT на URL <strong>%root%/users/1/services/2/tarif</strong> JSON-текста.<br/>
                Например через curl в командной строке <pre>curl -i -T put  http:/skynet.local/users/1/services/2/tarif</pre>
                для этого должен быть файл <strong>put</strong> содержимое которого например <code>{"tarif_id": "4"}</code>
                <br/>Результат JSON: <code>{"result":"ok"}</code>
            </li>
        </ul>
    </body>
</html>