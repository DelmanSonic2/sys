<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

$many = false;

function getHistory($phone)
{



    $history = DB::query('SELECT tr.client_phone, tr.created, tr.sum, tr.discount, tr.total, tr.points, tr.type, tr.promotion, p.name AS point, e.name AS employee
                                FROM ' . DB_TRANSACTIONS . ' tr
                                JOIN ' . DB_PARTNER_POINTS . ' p ON p.id = tr.point
                                LEFT JOIN ' . DB_EMPLOYEES . ' e ON e.id = tr.employee
                                WHERE tr.client_phone = "' . $phone . '"
                                ORDER BY tr.created DESC');

    require ROOT . 'api/classes/TableHead.php';
    require ROOT . 'api/classes/ExportToFileClass.php';
    $f_class = new ExportToFile(false, TableHead::client_history(), 'История транзакций по номеру ' . $phone);

    $i = 1;

    while ($row = DB::getRow($history)) {

        $datetime = explode(' ', date("Y-m-d H:i:s", $row['created']));
        $promotion = (!$row['promotion']) ? 'Нет' : 'Да';
        $type = (!$row['type']) ? 'Картой' : 'Наличными';

        $f_class->data[] = array(
            'i' => $i,
            'point' => $row['point'],
            'employee' => $row['employee'],
            'phone' => $row['client_phone'],
            'date' => $datetime[0],
            'time' => $datetime[1],
            'sum' => round($row['sum'], 2),
            'discount' => round($row['discount'], 2),
            'total' => round($row['total'], 2),
            'points' => round($row['points'], 2),
            'type' => $type,
            'promotion' => $promotion
        );
    }

    $f_class->create(false, true);
}

if (!empty($_GET['phone'])) {

    $phone = DB::escape($_GET['phone']);

    getHistory($phone);
}

if (!empty($_POST) && !$phone) {

    $phone = DB::escape($_POST['phone']);
    $card = DB::escape($_POST['card']);

    $many = false;

    if ($card && !$phone) {
        $userdata = DB::select('phone', DB_CLIENTS, 'card_number = "' . $card . '" OR additional_card = "' . $card . '"');
        if (DB::getRecordCount($userdata) > 1)
            $many = true;
        else
            $phone = DB::getRow($userdata)['phone'];
    }

    if (!$many)
        getHistory($phone);
}
?>

<head>
    <style>
        .div {
            background-color: black;
        }

        .text {
            color: white;
            margin-top: 40px;
        }

        .center {
            position: absolute;
            margin: 0;
            top: 50%;
            left: 50%;
            -webkit-transform: translate(-50%, -50%);
            -moz-transform: translate(-50%, -50%);
            -ms-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
        }

        #input {
            width: 300px;
            margin-bottom: 10px;
            height: 50px;
            text-align: center;
        }

        #button {
            width: 300px;
        }
    </style>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
</head>

<body class="div">
    <form action="<?php

                    use Support\Pages;
                    use Support\DB;

                    echo SITE_URL . "api/client-history"; ?>" method="post">
        <div class="center">
            <input class="form-control" id="input" type="text" placeholder="Номер телефона клиента" name="phone" <?php

                                                                                                                    use Support\Pages;
                                                                                                                    use Support\DB;

                                                                                                                    if (!empty($_POST['phone'])) echo 'value="' . $_POST['phone'] . '"' ?>>
            <input class="form-control" id="input" type="text" placeholder="Номер карты клиента" name="card" <?php

                                                                                                                use Support\Pages;
                                                                                                                use Support\DB;

                                                                                                                if (!empty($_POST['card'])) echo 'value="' . $_POST['card'] . '"' ?>>
            <button type="submit" id="button" class="btn btn-warning">Скачать</button>
            <?php

            use Support\Pages;
            use Support\DB;

            if ($many) {
                echo '<p class="text">Выберите владельца картой:</p>';
                echo '<div class="list-group">';
                while ($row = DB::getRow($userdata)) {

                    echo '<a href="' . SITE_URL . 'api/client-history?phone=' . $row['phone'] . '" class="list-group-item list-group-item-action">' . $row['phone'] . '</a>';
                }
                echo '</div>';
            }
            ?>
        </div>
    </form>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>