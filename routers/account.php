<?php

    function tokenVerification($Link) {
        $token = substr(getallheaders()['Authorization'], 7);
        $userFromToken = $Link->query("SELECT userId from tokens where token='$token'")->fetch_assoc();
        if (!is_null($userFromToken)) {
            return $userFromToken['userId'];
        } else {
            setHTTPStatus("401", "Unauthorized");
            return null;
        }
    }

    function postRegister($Link, $requestData) {
        $isValidated = true;
        $validationErrors = [];
        
        $fullName = $requestData->body->fullName;
        $password = $requestData->body->password;
        $email = $requestData->body->email;
        $address = $requestData->body->address;
        $birthDate = $requestData->body->birthDate;
        $gender = $requestData->body->gender;
        $phoneNumber = $requestData->body->phoneNumber;

        if ($fullName == "") {
            $isValidated = false;
            $validationErrors[] = ["FullName" => "The FullName field is required."];
        }

        if (strlen($password) < 6) {
            $isValidated = false;
            $validationErrors[] = ["Password" => "The field Password must be a string or array type with a minimum length of '6'."];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $isValidated = false;
            $validationErrors[] = ["Email" => "The Email field is not a valid e-mail address."];
        }

        if (($gender == "")) {
            $isValidated = false;
            $validationErrors[] = ["Gender" => "The Gender field is required."];
        }
        elseif ($gender != "Male" and $gender != "Female") {
            $isValidated = false;
            $validationErrors[] = ["Gender" => "The Gender field is required with one of these values: Male or Female."];
        }

        if (!$isValidated) {
            $validationMessage = [];
            $validationMessage["errors"] = [];
            foreach($validationErrors as $field => $fieldError) {
                $validationMessage["errors"][$field] = $fieldError;
            }
            setHTTPStatus("400", $validationMessage);
            return;
        }

        $user = $Link->query("SELECT id from users where email='$email'")->fetch_assoc();

        if (is_null($user)) {
            $id = bin2hex(random_bytes(36));
            $password = hash("sha1", $password);
            $userInsertResult = $Link->query("INSERT INTO users (id, fullName, birthDate, gender, address, email, password, phoneNumber) VALUES ('$id', '$fullName', '$birthDate', '$gender', '$address', '$email', '$password', '$phoneNumber')");
            if ($userInsertResult) {
                $token = bin2hex(random_bytes(20));
                $tokenInsertResult = $Link->query("INSERT INTO tokens( token, userId ) VALUES('$token', '$id')");
                if (!$tokenInsertResult) {
                    setHTTPStatus("500", "Internal Server Error.");
                }
                else {
                    echo json_encode(["token" => $token]);
                }
            }
            else {
                setHTTPStatus("500", "Internal Server Error.");
            }
        }
        else {
            setHTTPStatus("400", "Username '$email' is already taken.");
        }
    }

    function postLogin($Link, $requestData) {
        $email = $requestData->body->email;
        $password  = hash("sha1", $requestData->body->password);

        $user = $Link->query("SELECT id from users where email='$email' AND password='$password'")->fetch_assoc();
        if (!is_null($user)) {
            $userId = $user['id'];
            $token = $Link->query("SELECT token from tokens where userId='$userId'")->fetch_assoc();
            if (is_null($token)) {
                $token = bin2hex(random_bytes(20));
                $tokenInsertResult = $Link->query("INSERT INTO tokens( token, userId ) VALUES('$token', '$userId')");
                if (!$tokenInsertResult) {
                    setHTTPStatus("500", "Internal Server Error.");
                }
                else {
                    echo json_encode(['token' => $token]);
                }
            }
            else {
                setHTTPStatus("400", "User is already logged in");
            }
        }
        else {
            setHTTPStatus("400", "Login failed");
        }
    }

    function postLogout($Link) {
        $token = substr(getallheaders()['Authorization'], 7);
        $userFromToken = $Link->query("SELECT userId from tokens where token='$token'")->fetch_assoc();
        if (!is_null($userFromToken)) {
            $user = $Link->query("DELETE from tokens where token = '$token'");
            if (!$user) {
                setHTTPStatus("500", "Internal Server Error.");
            }
            else {
                setHTTPStatus("200", "OK");
            }
        } 
        else {
            setHTTPStatus("401", "Unauthorized");
        }
    }

    function getProfile($Link) {
        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }
        $user = $Link->query("SELECT fullName, birthDate, gender, address, email, phoneNumber, id from users where id='$userId'")->fetch_assoc();
        if (!$user) {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
        else {
            echo json_encode($user);
        }
    }

    function putProfile($Link, $requestData) {
        $isValidated = true;
        $validationErrors = [];

        $userId = tokenVerification($Link);
        if (is_null($userId)) {
            return;
        }

        $user = $Link->query("SELECT * from users where id='$userId'")->fetch_assoc();
        if(!$user) {
            setHTTPStatus("400", "Bad request. Some data are strange");
        }
        else {
            $fullName = $requestData->body->fullName; 
            $birthDate = $requestData->body->birthDate;
            $gender = $requestData->body->gender;
            $address = $requestData->body->address;
            $phoneNumber = $requestData->body->phoneNumber;

            if ($fullName == "") {
                $isValidated = false;
                $validationErrors[] = ["FullName" => "The FullName field is required."];
            }

            if (($gender == "")) {
                $isValidated = false;
                $validationErrors[] = ["Gender" => "The Gender field is required."];
            }
            elseif ($gender != "Male" and $gender != "Female") {
                $isValidated = false;
                $validationErrors[] = ["Gender" => "The Gender field is required with one of these values: Male or Female."];
            }

            if (!$isValidated) {
                $validationMessage = [];
                $validationMessage["errors"] = [];
                foreach($validationErrors as $field => $fieldError) {
                    $validationMessage["errors"][$field] = $fieldError;
                }
                setHTTPStatus("400", $validationMessage);
                return;
            }

            $userUpdateResult = $Link->query("UPDATE users set fullName='$fullName', birthDate='$birthDate', gender='$gender', address='$address', phoneNumber='$phoneNumber' where id='$userId'");
            if (!$userUpdateResult) {
                setHTTPStatus("500", "Internal Server Error.");
            }
            else {
                setHTTPStatus("200", "OK");
            }
        }
    }

    function route($method, $urlList, $requestData) {
        global $Link;
        switch ($method) {
            case "POST":
                switch ($urlList[1]) {
                    case "register":
                        postRegister($Link, $requestData);
                        break;
                    case "login":
                        postLogin($Link, $requestData);
                        break;
                    case "logout":
                        postLogout($Link);
                        break;
                }
                break;
            case "GET":
                getProfile($Link);
                break;
            case "PUT":
                putProfile($Link, $requestData);
                break;
        }
    }

?>