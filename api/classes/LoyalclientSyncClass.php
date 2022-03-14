<?php
use Support\Pages;
use Support\DB;


class LoyalclientSyncClass
{
    private $db;
    private $request;
    private $url;

    public function __construct($db, $point, $transactionID, $json, $url)
    {
        // $this->db = $db;
        $this->url = $url;
        $this->request["api_key"] = LC_API_KEY;
        $this->request["uniq_id"] = $transactionID;
        $this->request["client"] = $json["loyalclient"]["client"];
        $this->request["employee"] = $json["loyalclient"]["employee"];
        $this->request["point_id"] = $point;
        $this->request["amount"] = $json["sum"];
        $this->request["total_amount"] = $json["total"];
        $this->request["discount"] = $json["discount"];
        $this->request["scores"] = $json["points"];
        $this->request["date"] = date("Y-m-d H:i:s", $json["date"]);
        foreach ($json["products"] as $prod) {
            $details = [];
            $details["product"] = $prod["name"];
            $details["quantity"] = $prod["count"];
            $details["price"] = $prod["price"];
            $details["units"] = $prod["bulk"];
            $details["discount"] = $prod["discount"];
            $details["amount"] = $prod["price"] * $prod["count"];
            $details["total_amount"] = $prod["total"];
            $this->request["details"][] = $details;
        }
        foreach ($json["promotions"] as $p_val) {
            $details = [];
            $details["product"] = $p_val["promotion_name"];
            $details["quantity"] = $p_val["count"];
            $details["price"] = $p_val["price"];
            $details["units"] = 'шт';
            $details["discount"] = $p_val["discount"];
            $details["amount"] = $p_val["price"] * $p_val["count"];
            $details["total_amount"] = $p_val["total"];
            $this->request["details"][] = $details;
        }
    }

    public function Request()
    {
        $ch = curl_init(LC_API_URL . $this->url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->request));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        switch ($httpcode) {
            case 200:
            case 201:
                $this->Enqueue('completed', $result);
                $result = json_decode($result, true);
                if ($result["code"] == 200 || $result["code"] == 201) {
                } else {
                }
                break;

            default:
                $this->Enqueue();
                break;
        }
        return [
            'http_code' => $httpcode,
            'result' => $result
        ];
    }

    public function Enqueue($status = 'waiting', $response = null)
    {
        $fields = array(
            "url" => $this->url,
            "type" => "POST",
            "status" => $status,
            "parameters" => DB::escape(json_encode($this->request)),
            "response" => DB::escape($response),
            "retry_at" => time(),
            "created_at" => time(),
            "updated_at" => time()
        );
        @DB::insert($fields, DB_LOYALCLIENT_REQUEST_QUEUE);
    }

    public static function Revert($db, $request, $url)
    {
        $url = $url . "?api_key=" . LC_API_KEY . "&uniq_id=" . $request;
        $ch = curl_init(LC_API_URL . $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        switch ($httpcode) {
            case 200:
            case 201:
                $result = json_decode($result, true);
                if ($result["code"] == 200 || $result["code"] == 201) {
                } else {
                }
                break;

            default:
                $fields = array(
                    "url" => $url,
                    "type" => "DELETE",
                    "status" => "waiting",
                    "retry_at" => time(),
                    "created_at" => time(),
                    "updated_at" => time()
                );
                DB::insert($fields, DB_LOYALCLIENT_REQUEST_QUEUE);
                break;
        }
        return [
            'http_code' => $httpcode,
            'result' => $result
        ];
    }
}