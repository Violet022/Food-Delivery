<?php

    function tokenVerification($Link) {
        $token = substr(getallheaders()['Authorization'], 7);
        $userFromToken = $Link->query("SELECT userId from tokens where token='$token'")->fetch_assoc();
        if (!is_null($userFromToken)) {
            return $userFromToken['userId'];
        } else {
            setHTTPStatus("401");
            return null;
        }
    } 
 
    function getOrderInfo($Link, $urlList) {
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $orderId = $urlList[1];
        $answer = [];
        $orderInfo = $Link->query("SELECT * FROM orders WHERE id='$orderId' AND userId='$userId'")->fetch_assoc();
        if(!is_null($orderInfo)) {
            $answer["id"] = $orderInfo["id"];
            $answer["deliveryTime"] = $orderInfo["deliveryTime"];
            $answer["orderTime"] = $orderInfo["orderTime"];
            $answer["status"] = $orderInfo["status"];
            $answer["price"] = $orderInfo["price"];

            $answer["dishes"] = [];
            $orderDishes = $Link->query("SELECT dishesInf.dishId, dishesInf.name, dishesInf.price, dishesInf.amount, dishesInf.image FROM orders AS ords 
                                            LEFT JOIN 
                                                (SELECT od.dishId,od.orderId, od.amount, ds.name, ds.price, ds.image FROM orderDishes AS od 
                                                    LEFT JOIN dishes AS ds 
                                                    ON od.dishId = ds.id) AS dishesInf 
                                            ON ords.id = dishesInf.orderId 
                                            WHERE ords.id='$orderId' AND ords.userId='$userId'");
            while ($row = $orderDishes->fetch_assoc()) {
                $totalPrice = (int)$row["price"] * (int)$row["amount"];
    
                $answer["dishes"][] = [
                    "id" => $row["dishId"],
                    "name" => $row["name"],
                    "price" => (int)$row["price"],
                    "totalPrice" => $totalPrice,
                    "amount" => (int)$row["amount"],
                    "image" => $row["image"]
                ];
            }
            $answer["address"] = $orderInfo["address"];

            if (is_null($answer)) {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
            else {
                echo json_encode($answer);
            }
        }
        else {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
    }

    function getAllOrders($Link) {
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $answer = [];
        $userOrders = $Link->query("SELECT id, deliveryTime, orderTime, status, price FROM orders WHERE userId='$userId'");
        while ($row = $userOrders->fetch_assoc()) {
            $answer[] = [
                "id" => $row["id"],
                "deliveryTime" => $row["deliveryTime"],
                "orderTime" => $row["orderTime"],
                "status" => $row["status"],
                "price" => (int)$row["price"],
            ];
        }

        if (is_null($answer)) {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
        else {
            echo json_encode($answer);
        }
    }

    function postOrder($Link, $requestData) {
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $isValidated = true;
        $validationErrors = [];
        $dataDiff = 60;

        $deliveryTime = $requestData->body->deliveryTime;
        $address = $requestData->body->address;
        $orderTime = date("Y-m-d H:i:s"); 

        if (is_null($deliveryTime)) {
            $isValidated = false;
            $validationErrors[] = ["DeliveryTime" => "The DeliveryTime field is required."];
        }
        if (is_null($address)) {
            $isValidated = false;
            $validationErrors[] = ["Address" => "The Address field is required."];
        }
        // if ((int)$diffBetweenDelTimeAndOrdTimeInMins < $dataDiff) {
        //     $isValidated = false;
        //     $validationErrors[] = ["DeliveryTime" => "The difference between the OrderTime and the DeliveryTime must be at least '$dataDiff' minutes."];
        // }

        if (!$isValidated) {
            $validationMessage = [];
            $validationMessage["errors"] = [];
            foreach($validationErrors as $field => $fieldError) {
                $validationMessage["errors"][$field] = $fieldError;
            }
            setHTTPStatus("400", $validationMessage);
            return;
        }

        $userBasketDishes = $Link->query("SELECT dishId, amount, price FROM basketDishes LEFT JOIN dishes ON basketDishes.dishId = dishes.id WHERE userId='$userId'");
        if (!is_null($userBasketDishes)) {
            $id = bin2hex(random_bytes(36));
            $orderPrice = 0;

            $orderInsertResult = $Link->query("INSERT INTO orders( id, deliveryTime, orderTime, status, price, address, userId ) VALUES('$id', '$deliveryTime', '$orderTime', 'InProcess', '$orderPrice', '$address', '$userId')");

            while ($row = $userBasketDishes->fetch_assoc()) {
                $dishId = $row["dishId"];
                $dishAmount = (int)$row["amount"];
                $dishPrice = (int)$row["price"];
                $orderPrice += $dishAmount * $dishPrice;

                $orderDishesInsertResult = $Link->query("INSERT INTO orderDishes( orderId, dishId, amount ) VALUES('$id', '$dishId', '$dishAmount')");
                if (!$orderDishesInsertResult) {
                    setHTTPStatus("400", "Bad request. Some data are strange");
                    return;
                }
            }

            $orderId = mb_substr($id, 0, 36);
            $orderUpdateResult = $Link->query("UPDATE orders SET price='$orderPrice' WHERE id='$orderId'");
            $busketDishesDeleteResult = $Link->query("DELETE FROM basketDishes WHERE userId='$userId'");

            if ($orderUpdateResult && $busketDishesDeleteResult) {
                setHTTPStatus("200", "OК");
            }
            else {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }

        }
        else {
            setHTTPStatus("400", "Basket is empty. Add dishes to make order.");
        }

    }

    function postConfirmDelivery($Link, $urlList) {
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $orderId = $urlList[1];
        $orderStatusUpdateResult = $Link->query("UPDATE orders SET status='Delivered' where id='$orderId'");
        if ($orderStatusUpdateResult) {
            setHTTPStatus("200", "OK");
        }
        else {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
    }

    function route($method, $urlList, $requestData) {
        global $Link;
        switch ($method) {
            case "GET":
                if ($urlList[1]) {
                    getOrderInfo($Link, $urlList);
                }
                else {
                    getAllOrders($Link); 
                }
                break;
            case "POST":
                if ($urlList[1] && $urlList[2] == "status") {
                    postConfirmDelivery($Link, $urlList); 
                }
                elseif (!$urlList[1] && !$urlList[2]) {
                    postOrder($Link, $requestData); // Creating the order from dishes 
                }
                break;
        }
    }

?>