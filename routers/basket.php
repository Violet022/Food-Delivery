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
    
    function getBasket($Link) {
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $userBasket = [];
        $userBasketDishes = $Link->query("SELECT dishId, name, price, amount, image FROM basketDishes LEFT JOIN dishes ON basketDishes.dishId = dishes.id WHERE userId='$userId'");
        while ($row = $userBasketDishes->fetch_assoc()) {
            $totalPrice = (int)$row["price"] * (int)$row["amount"];

            $userBasket[] = [
                "id" => $row["dishId"],
                "name" => $row["name"],
                "price" => (int)$row["price"],
                "totalPrice" => $totalPrice,
                "amount" => (int)$row["amount"],
                "image" => $row["image"]
            ];
        }

        echo json_encode($userBasket);
    }

    function postDishToTheBasket($Link, $urlList) {
        $userId = tokenVerification($Link); 
        $dishId = $urlList[1]; 
        $amount = 1;
        if (is_null($userId)) {
            return;
        }

        $dishInBasket = $Link->query("SELECT * from basketDishes where dishId='$dishId' AND userId='$userId'")->fetch_assoc();
        if (is_null($dishInBasket)) {
            $basketInsertResult = $Link->query("INSERT INTO basketDishes (userId, dishId, amount) VALUES ('$userId', '$dishId', '$amount')");
            if ($basketInsertResult) {
                setHTTPStatus("200", "OK");
            }
            else {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
        }
        else {
            $amount = $dishInBasket["amount"] + 1;
            $basketUpdateResult = $Link->query("UPDATE basketDishes SET amount='$amount' where dishId='$dishId' AND userId='$userId'");
            if ($basketUpdateResult) {
                setHTTPStatus("200", "OK");
            }
            else {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
        }
    }

    function deleteDishFromTheBasket($Link, $urlList, $requestData) {
        $userId = tokenVerification($Link);
        $dishId = $urlList[1];
        $isIncrease = $requestData->parameters->increase;
        if (is_null($userId)) {
            return;
        }

        if (is_null($isIncrease)) {
            $isIncrease = false;
        }

        $dishInBasket = $Link->query("SELECT * FROM basketDishes WHERE dishId='$dishId' AND userId='$userId'")->fetch_assoc();
        $currentAmount = (int)$dishInBasket["amount"];
        if ($currentAmount == 1) $isIncrease = false;

        if ($isIncrease == "true") {
            $newAmount = $currentAmount - 1;
            $basketUpdateResult = $Link->query("UPDATE basketDishes SET amount='$newAmount' where dishId='$dishId' AND userId='$userId'");
            if ($basketUpdateResult) {
                setHTTPStatus("200", "OK");
            }
            else {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
        }
        else {
            $basketDeleteResult = $Link->query("DELETE FROM basketDishes WHERE dishId='$dishId' AND userId='$userId'");
            if ($basketDeleteResult) {
                setHTTPStatus("200", "OK");
            }
            else {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
        }

    }

    function route($method, $urlList, $requestData) {
        global $Link;
        switch ($method) {
            case "GET":
                getBasket($Link); 
                break;
            case "POST":
                postDishToTheBasket($Link, $urlList); 
                break;
            case "DELETE":
                deleteDishFromTheBasket($Link, $urlList, $requestData); 
                break;
        }
    }

?>