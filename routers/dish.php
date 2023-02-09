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

    function getUserRatingPossibility($Link, $urlList) {
        $dishId = $urlList[1];
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }
        
        $userOrdersChecking = $Link->query("SELECT COUNT(*) AS dishesOrderNum FROM orders LEFT JOIN orderDishes ON orders.id = orderDishes.orderId WHERE userId='$userId' AND dishId ='$dishId'")->fetch_assoc();
        if ($userOrdersChecking) {
            if ((int)$userOrdersChecking["dishesOrderNum"] > 0) {
                echo json_encode(true);
            }
            else {
                echo json_encode(false);
            }
        }
        else {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
    }

    function getDishInfo($Link, $urlList) {
        $dishId = $urlList[1];
        $dish = $Link->query("SELECT * from dishes where id='$dishId'")->fetch_assoc();
        if (!$dish) {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
        else {
            $answer = [];
            $isVegetarian = $dish["vegetarian"] == "0" ? false : true;

            $ratingInfo = $Link->query("SELECT SUM(rating) as rating, COUNT(*) as ratingsNumber from ratings where dishId='$dishId'")->fetch_assoc();
            if ((int)$ratingInfo["ratingsNumber"] != 0) {
                $currentDishRating = $ratingInfo["rating"] / $ratingInfo["ratingsNumber"];
            }
            else {
                $currentDishRating = 0;
            }

            $answer[] = [
                "id" => $dish["id"],
                "name" => $dish["name"],
                "description" => $dish["description"],
                "price" => (int)$dish["price"],
                "image" => $dish["image"],
                "vegetarian" => $isVegetarian,
                "rating" => $currentDishRating,
                "category" => $dish["category"],
            ];

            echo json_encode($answer);
        }
    }

    function getCurrentPageDishes($Link, $requestData) {
        $categories = $requestData->parameters->categories;
        $vegetarian = $requestData->parameters->vegetarian;
        $sorting = mb_strtolower($requestData->parameters->sorting);
        $queryString = "SELECT * FROM dishes";
        $whereArray = [];
        $sortingString;

        if (!is_null($categories)) {
            $whereArray[] = "category='$categories'";
        }

        if (!is_null($vegetarian)) {
            $vegetarianRange = $vegetarian == "false" ? "(0, 1)" : "(1)";
            $whereArray[] = "vegetarian in " . $vegetarianRange;
        }
 
        if (!is_null($sorting)) {
            if (substr($sorting, -3) == "asc") {
                $sortingField = substr($sorting, 0, -3);
                $sortingOrder = " ASC";
            }
            else {
                $sortingField = substr($sorting, 0, -4);
                $sortingOrder = " DESC";
            }
            $sortingString = "ORDER BY " . $sortingField . $sortingOrder;
        }

        if (count($whereArray) != 0) {
            $queryString.= " WHERE " . implode(" AND ", $whereArray);
        }
        if (!is_null($sortingString)) {
            $queryString.= $sortingString;
        }

        $result = $Link->query($queryString);
        $allDishes = mysqli_fetch_all($result, MYSQLI_ASSOC);

        $pageSize = 5;
        $pageCount = ceil(count($allDishes) / $pageSize);
        $currentPage = $requestData->parameters->page != null ? (int)$requestData->parameters->page : 1;

        $firstDish = ($pageSize * ($currentPage - 1));
        $currentPageDishes = $Link->query($queryString . " LIMIT $firstDish, $pageSize");

        $answer = [];
        $answer["dishes"] = [];
        while ($row = $currentPageDishes->fetch_assoc()) {
            $isVegetarian = $row["vegetarian"] == "0" ? false : true;

            $dishId = $row["id"];
            $ratingInfo = $Link->query("SELECT SUM(rating) as rating, COUNT(*) as ratingsNumber from ratings where dishId='$dishId'")->fetch_assoc();
            if ((int)$ratingInfo["ratingsNumber"] != 0) {
                $currentDishRating = $ratingInfo["rating"] / $ratingInfo["ratingsNumber"];
            }
            else {
                $currentDishRating = 0;
            }

            $answer["dishes"][] = [
                "name" => $row["name"],
                "description" => $row["description"],
                "price" => (int)$row["price"],
                "image" => $row["image"],
                "vegetarian" => $isVegetarian,
                "rating" => $currentDishRating,
                "category" => $row["category"],
                "id" => $row["id"]
            ];
        }

        $answer["pagination"] = ["size" => $pageSize, "count" => $pageCount, "current" => $currentPage];
        if (is_null($answer)) {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
        else {
            echo json_encode($answer);
        }
    }

    function postDishRating($Link, $urlList, $requestData) {
        $dishId = $urlList[1];
        $ratingScore = $requestData->parameters->ratingScore;
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $dishRating = $Link->query("SELECT rating from ratings where userId='$userId'")->fetch_assoc();
        if (is_null($dishRating)) {
            $ratingInsertResult = $Link->query("INSERT INTO ratings( dishId, userId, rating ) VALUES('$dishId', '$userId', '$ratingScore')");
            if (!$ratingInsertResult) {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
            else {
                setHTTPStatus("200", "OK");
            }
        }
        else {
            $ratingUpdateResult = $Link->query("UPDATE ratings set rating='$ratingScore' where userId='$userId'");
            if (!$ratingUpdateResult) {
                setHTTPStatus("400", "Bad request. Some data are strange");
            }
            else {
                setHTTPStatus("200", "OK");
            }
        }
    }

    function route($method, $urlList, $requestData) {
        global $Link;
        switch ($method) {
            case "GET":
                if ($urlList[1]) {
                    if ($urlList[2] == "rating" && $urlList[3] == "check") {
                        getUserRatingPossibility($Link, $urlList);
                    }
                    else {
                        getDishInfo($Link, $urlList); 
                    }
                }
                else {
                    getCurrentPageDishes($Link, $requestData); 
                }
                break;
            case "POST":
                postDishRating($Link, $urlList, $requestData); 
                break;
        }
    }

?>