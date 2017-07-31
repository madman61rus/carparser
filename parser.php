<?php

function prepare_line($row_line)
{
    $string = str_replace('"', '', $row_line);

    $matches = [];
    preg_match('/\<a [а-яА-Яa-zA-z\d\.\=\"\+\/?&;\>!\|\-´`\' (),\,ÉéÜÁÖ\_]*\<\/a>,/u', $string, $matches);

    $string = str_replace($matches[0], '', $string);

    $string_array = explode(',', $string);

    $carbody_index = 0;
    $engines_codes = [];


    #Количество кодов двигателей может быть много через запятую

    if (count($string_array) > 8) {
        for ($i = 2; $i < count($string_array); $i++) {
            if (preg_match('/[а-яА-Я]/', $string_array[$i], $match)) {
                $carbody_index = $i;
                break;
            }
        }

        for ($i = $carbody_index - 1; $i > 2; $i--) {
            $engines_codes[] = $string_array[$i];
        }
    } else {
        $carbody_index = 4;
        $engines_codes[] = $string_array[3];
    }

    $matches_url = [];
    $tmp_url = preg_match('<a[\w="\/?&; ]*>', $matches[0], $matches_url);

    $url = str_replace($matches_url[0], '', $matches[0]);
    $url = str_replace(['<', '>', 'a', '/', ','], '', $url);

    $numberOfCharacter = mb_strlen($string_array[0]) + mb_strlen($string_array[1]) + 1;
    $podmodel = trim(mb_substr($url, $numberOfCharacter, strlen($url)));


    $tmp_url = explode('&amp;', $matches_url[0]);

    $dates = explode('-', $string_array[2]);
    $start_month = substr(trim($dates[0]), 0, 2);
    $start_year = substr(trim($dates[0]), 3, 4);

    if (trim($dates[1]) != "") {
        $stop_year = substr(trim($dates[1]), 3, 4);
        $stop_month = substr(trim($dates[1]), 0, 2);
    } else {
        $stop_month = "0";
        $stop_year = "0";
    }

    # Array for one type of a car
    $line = [
        'manufacturer' => $string_array[0],
        'title_model' => $string_array[1],
        'title_sub_model' => $podmodel,
        'start_year' => $start_year,
        'start_month' => $start_month,
        'stop_year' => $stop_year,
        'stop_month' => $stop_month,
        'enginescode' => implode(',', $engines_codes),
        'carsbody' => $string_array[$carbody_index],
        'engine' => $string_array[$carbody_index + 1],
        'power' => $string_array[$carbody_index + 2],
        'enginestype' => ($string_array[$carbody_index + 3] != '') ? trim($string_array[$carbody_index + 3]) : 'null',
        'models_url' => "url",
        'cylinders' => 0,
        'clapans' => 0,
        'drive_type' => 1,
        'fuel_prepare' => 1,
        'man_id' => str_replace('a href=/catalog/?man=', '', $tmp_url[0]),
        'model_id' => str_replace("model=", '', $tmp_url[1]),
        'variant_id' => str_replace("modelVariant=", '', $tmp_url[2]),
    ];

    return $line;

}

function filling_additional_tables($cars_array, &$carsBodys, &$enginesTypes, &$manufacturers)
{

    foreach ($cars_array as $car) {
        if ($car['manufacturer'] && (!in_array(trim($car['manufacturer']), $manufacturers))) $manufacturers[] = trim($car['manufacturer']);
        if ($car['carsbody'] && (!in_array(trim(mb_strtolower($car['carsbody'])), $carsBodys))) $carsBodys[] = mb_strtolower(trim($car['carsbody']));
        if ((ord($car['enginestype']) != 13) &&
            !empty($car['enginestype']) &&
            (!in_array(mb_strtolower(trim($car['enginestype'])), $enginesTypes))
        ) $enginesTypes[] = mb_strtolower(trim($car['enginestype']));

    }

}

function write_data_to_additional_bd(&$db_connection, $dbname = '', $data = [])
{
    $string_query = '';

    foreach ($data as $dt) {
        $string_query .= "INSERT INTO {$dbname} (title) VALUES ('" . trim($dt) . "');  ";
    }

    $db_connection->exec($string_query);

}

function write_main_data(&$db_connection, $dbname, $data)
{


    foreach ($data as $dt) {
        if (ord($dt['enginestype']) == 13) {
            $query = $db_connection->prepare("SELECT id FROM EnginesTypes WHERE title='null';");
            $query->execute();
            $enginestype_id = $query->fetchColumn();

        } else {
            $query = $db_connection->prepare("SELECT id FROM EnginesTypes WHERE title='" . $dt['enginestype'] . "';");
            $query->execute();
            $enginestype_id = $query->fetchColumn();
            if ($enginestype_id == 0) {
                $enginestype_id = 9;
            }
        }

        $query = $db_connection->prepare("SELECT id FROM CarsBody WHERE title='" . $dt['carsbody'] . "';");
        $query->execute();
        $carsbody_id = $query->fetchColumn();

        $query = $db_connection->prepare("SELECT id FROM Manufacturer WHERE title='" . $dt['manufacturer'] . "';");
        $query->execute();
        $manufacturer_id = $query->fetchColumn();

        if (empty($dt['title_model'])) continue;

        $stmt = $db_connection->prepare("INSERT INTO Cars (
manufacturer,
 title_model,
  title_sub_model,
   start_year,
    start_month,
     stop_year,
      stop_month,
       enginescode,
        carsbody,
         engine,
          power,
           enginetype,
            models_url,
             cylinders,
              clapans,
               drive_type,
                fuel_prepare,
                 man_id,
                  model_id,
                   variant_id)
          VALUES (
          :manufacturer,
           :title_model,
            :title_sub_model,
             :start_year,
              :start_month,
               :stop_year,
                :stop_month,
                 :enginescode,
                  :carsbody,
                   :engine,
                    :power,
                     :enginestype,
                      :models_url,
                       :cylinders,
                        :clapans,
                         :drive_type,
                          :fuel_prepare,
                           :man_id,
                            :model_id,
                             :variant_id)");


        $stmt->bindValue(':manufacturer', $manufacturer_id, PDO::PARAM_INT);
        $stmt->bindParam(':title_model', $dt['title_model']);
        $stmt->bindParam(':title_sub_model', $dt['title_sub_model']);
        $stmt->bindValue(':start_year', $dt['start_year'], PDO::PARAM_INT);
        $stmt->bindValue(':start_month', $dt['start_month'], PDO::PARAM_INT);
        $stmt->bindValue(':stop_year', $dt['stop_year'], PDO::PARAM_INT);
        $stmt->bindValue(':stop_month', $dt['stop_month'], PDO::PARAM_INT);
        $stmt->bindParam(':enginescode', $dt['enginescode']);
        $stmt->bindParam(':carsbody', $carsbody_id);
        $stmt->bindValue(':engine', $dt['engine'], PDO::PARAM_STR);
        $stmt->bindValue(':power', $dt['power'], PDO::PARAM_INT);
        $stmt->bindValue(':enginestype', $enginestype_id, PDO::PARAM_INT);
        $stmt->bindValue(':models_url', $dt['models_url']);
        $stmt->bindValue(':cylinders', $dt['cylinders']);
        $stmt->bindValue(':clapans', $dt['clapans']);
        $stmt->bindValue(':drive_type', $dt['drive_type']);
        $stmt->bindValue(':fuel_prepare', $dt['fuel_prepare']);
        $stmt->bindParam(':man_id', $dt['man_id']);
        $stmt->bindParam(':model_id', $dt['model_id']);
        $stmt->bindParam(':variant_id', $dt['variant_id']);

        try {
            $stmt->execute();
        } catch (PDOException $exception) {

            var_dump($exception);
            echo "<pre>";
            echo var_dump($stmt->fetchAll());
            echo "<pre>";


            echo "<pre>";
            echo print_r($dt);
            echo "<pre>";
            echo "id - " . ord($enginestype_id);
            echo "id - " . ord($enginestype_id);
            file_put_contents('errors.txt', $exception . " -- " . var_dump($dt), FILE_APPEND);
        }
    }


}

const USER = 'root';
const PASSWORD = 'coolroot';
const FILENAME = 'omaha.txt';

$dbh = new PDO('mysql:host=localhost;dbname=Automodeli', USER, PASSWORD);
$dbh->query("SET NAMES utf8");
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$row_cars = []; # array of cars
$carsBodys = [];
$enginesTypes = [];
$manufacturers = [];

$row_cars = file(FILENAME);

filling_additional_tables($row_cars, $carsBodys, $enginesTypes, $manufacturers);
write_data_to_additional_bd($dbh, 'CarsBody', $carsBodys);
write_data_to_additional_bd($dbh, 'Manufacturer', $manufacturers);
write_data_to_additional_bd($dbh, 'EnginesTypes', $enginesTypes);
write_main_data($dbh, 'Cars', $row_cars);


