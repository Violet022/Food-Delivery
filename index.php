<?php
    include_once 'helpers/headers.php';

    global $Link;
    header('Content-type:application/json');

    function getData($method) {
        $data = new stdClass();
        $params = [];
        if ($method != "GET")
        {
            $data->body = json_decode(file_get_contents('php://input'));
        }
        $dataGet = $_GET;
        foreach ($dataGet as $key => $value) 
        {
            if ($key != "q")
            {
                $params[$key] = $value;
            }
        }
        $params = json_encode($params);
        $data->parameters = json_decode($params);
        return $data;
    }

    function getMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    $Link = mysqli_connect("127.0.0.1", "food_delivery", "password", "food_delivery");

    if (!$Link) {
        setHTTPStatus("500", "DB Connection error: " . mysqli_connect_error());
        exit;
    }

    $url = isset($_GET['q']) ? $_GET['q'] : '';
    $url = rtrim($url, '/');
    $urlList = explode('/', $url);

    $router = $urlList[0];
    $requestData = getData(getMethod());
    $method = getMethod();
    
    if (file_exists(realpath(dirname(__FILE__)) . '/routers/' . $router . '.php')) {
        include_once 'routers/' . $router . '.php';
        route($method, $urlList, $requestData);
    } 
    else {
       setHTTPStatus("404", "Not found");
    }

    mysqli_close($Link);

    return;
?> 