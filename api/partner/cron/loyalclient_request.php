<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

$requests = [];
$query = DB::query("SELECT * FROM " . DB_LOYALCLIENT_REQUEST_QUEUE . "
                               WHERE status = 'waiting' AND retry_at < " . time() . " ORDER BY id ASC LIMIT 100");
if (DB::getRecordCount($query) == 0) return;
while ($row = DB::getRow($query))
    $requests[] = $row;

foreach ($requests as $request) {
    $ch = curl_init(LC_API_URL . $request["url"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    if (($request["type"]) == "POST") {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request["parameters"]);
    } else if (($request["type"]) == "DELETE")
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    switch ($httpcode) {
        case 200:
        case 201:
            $result = json_decode($result, true);
            if ($result["code"] == 200 || $result["code"] == 201)
                DB::update(
                    ["status" => 'completed', "updated_at" => time()],
                    DB_LOYALCLIENT_REQUEST_QUEUE,
                    "id = " . $request["id"]
                );
            else
                DB::update(
                    ["status" => 'error', "updated_at" => time()],
                    DB_LOYALCLIENT_REQUEST_QUEUE,
                    "id = " . $request["id"]
                );
            break;

        default:
            DB::update(
                ["tries" => $request["tries"] + 1, "retry_at" => time() + rand(60, 180), "updated_at" => time()],
                DB_LOYALCLIENT_REQUEST_QUEUE,
                "id = " . $request["id"]
            );
            break;
    }
}
