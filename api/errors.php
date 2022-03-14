<?php
use Support\Pages;
use Support\DB;



define('DB_EVENT_LOG', 'event_log');

if(!empty($_GET['id'])){

    $id = DB::escape($_GET['id']);

    $data = $data = DB::select('*', DB_EVENT_LOG, 'id = '.$id, '', 1);

    $data = DB::getRow($data);

    echo $data['description'];

}
else{

?>

<head>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <style>
    .btn-circle {
        width: 38px;
        height: 38px;
        border-radius: 19px;
        text-align: center;
        padding-left: 0;
        padding-right: 0;
        font-size: 16px;
    }
    </style>
</head>
<body>

<?php
use Support\Pages;
use Support\DB;

    $data = DB::select('*', DB_EVENT_LOG, '', 'id DESC', 1000);

    $types = array( 1 => 'Сообщение',
                    2 => 'Предупреждение',
                    3 => 'Ошибка');

    $types_code = array(
        1 => 'badge-info',
        2 => 'badge-warning',
        3 => 'badge-danger'
    );
    
    echo '<div class="list-group">';

    $prev_date = 0;

    while($row = DB::getRow($data)){
            
        if(date('Y-m-d', $row['createdon']) != $prev_date)
            echo '</div>
            </div>
          </div>
            <div class="accordion" id="accordionExample">
            <div class="card">
              <div class="card-header" id="headingOne">
                <h2 class="mb-0">
                  <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse'.$row['id'].'" aria-expanded="true" aria-controls="collapse'.$row['id'].'">
            '.date('Y-m-d', $row['createdon']).'
            </button>
            </h2>
          </div>
      
          <div id="collapse'.$row['id'].'" class="collapse" aria-labelledby="headingOne" data-parent="#accordionExample">
            <div class="card-body">';

        echo '<a href="'.SITE_URL.'api/eventlog?id='.$row['id'].'" class="list-group-item list-group-item-action"><span class="badge badge-secondary">'.date('H:i:s', $row['createdon']).'</span> '.$row['source'].' <span class="badge badge-pill '.$types_code[$row['type']].'">'.$types[$row['type']].'</span></a>';

        $prev_date = date('Y-m-d', $row['createdon']);

    }
    echo '</div>';

}

?>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>