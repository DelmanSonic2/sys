<?php
use Support\Pages;
use Support\DB;

class Order{

    public static function statistics_clients($field, $order){

        $access_sort = array(
            'name' => 'c.name',
            'phone' => 'c.phone',
            'registration_date' => 'c.registration_date',
            'without_discount' => 'without_discount',
            'cash' => 'cash',
            'card' => 'card',
            'points' => 'points',
            'profit' => 'profit',
            'check_count' => 'check_count',
            'avg_check' => 'avg_check'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY without_discount DESC, c.name ASC, c.phone ASC';

        return $sorting;

    }

    public static function statistics_employees($field, $order){

        $access_sort = array(
            'name' => 'e.name',
            'revenue' => 'revenue',
            'profit' => 'profit',
            'check_count' => 'check_count',
            'avg_check' => 'avg_check'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY revenue DESC, e.name ASC';

        return $sorting;

    }

    public static function statistics_points($field, $order){

        $access_sort = array(
            'partner' => 'p.name',
            'name' => 'tr.name',
            'address' => 'tr.address',
            'total' => 'total',
            'cost_price' => 'cost_price',
            'profit' => 'profit',
            'check_count' => 'check_count',
            'avg_check' => 'avg_check'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY SUM(t.total) DESC';

        return $sorting;

    }

    public static function statistics_products($field, $order){

        $access_sort = array(
            'product' => 'p.name',
            'category' => 'c.name',
            'count' => 'count',
            'cost_price' => 'cost_price',
            'without_discount' => 'without_discount',
            'discount' => 'discount_percent',
            'revenue' => 'revenue',
            'profit' => 'profit'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY without_discount DESC, p.name ASC';

        return $sorting;

    }

    public static function statistics_items($field, $order){

        $access_sort = array(
            'name' => 'i.name',
            'category' => 'ic.name',
            'count' => 'count',
            'price' => 'price',
            'total' => 'total'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY total ASC, i.name ASC';

        return $sorting;

    }

    public static function statistics_promotions($field, $order){

        $access_sort = array(
            'name' => 'ti.promotion_name',
            'count' => 'count',
            'cost_price' => 'cost_price',
            'total' => 'total',
            'profit' => 'profit'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY total DESC, ti.id DESC';

        return $sorting;

    }

    public static function statistics_checks($field, $order){

        $access_sort = array(
            'employee' => 'e.name',
            'created' => 'tr.created',
            'point' => 'p.name',
            'client_name' => 'c.name',
            'client_phone' => 'tr.client_phone',
            'sum' => 'tr.sum',
            'total' => 'tr.total',
            'discount' => 'tr.discount',
            'minus_points' => 'tr.points',
            'plus_points' => 'tr.points',
            'profit' => 'tr.profit'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY tr.created DESC';

        return $sorting;

    }

    public static function statistics_payments($field, $order){

        $access_sort = array(
            'date' => 'YEAR(tr.created_datetime) '.$order.', MONTH(tr.created_datetime) '.$order.', DAY(tr.created_datetime) '.$order,
            'checks' => 'checks '.$order,
            'cash' => 'cash '.$order,
            'card' => 'card '.$order,
            'points' => 'points '.$order,
            'total' => 'SUM(tr.total) '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];
        else
            $sorting = 'ORDER BY YEAR(tr.created_datetime) DESC, MONTH(tr.created_datetime) DESC, DAY(tr.created_datetime) DESC';

        return $sorting;

    }

    public static function statistics_removal_report($field, $order){

        $access_sort = array(
            'name' => 'p.name',
            'category' => 'pc.name',
            'sales' => 'tr.tr_count',
            'count' => 't.count',
            'removal_to_sale' => '(t.count / (t.count + tr.tr_count))',
            'profit' => 'tr.profit',
            'sum' => 't.sum',
            'removal_to_profit' => '(t.sum / tr.profit)'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY p.name ASC';

        return $sorting;

    }

    public static function finances_transactions($field, $order){

        $access_sort = array(
            'dt1' => 't.date',                      //Дата
            'dt2' => 'TIME(FROM_UNIXTIME(t.date))', //Время
            'dt3' => 'c.name',                      //Категория
            'dt4' => 't.comment',                   //Комментарий
            'dt5' => 't.sum',                       //Сумма
            'dt6' => 't.balance',                   //Баланс
            'dt7' => 'p.name',                      //Точка
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY t.date DESC';

        return $sorting;

    }

    public static function finances_report($field, $order){

        $access_sort = array(
            'dt1' => 'c.name',
            'dt2' => 't.date',
            'dt3' => 'sum'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY t.date DESC';

        return $sorting;

    }

    public static function finances_shifts($field, $order){

        $access_sort = array(
            'employee' => 'e.name',
            'closed' => 'sh.shift_closed',
            'shift_from' => 'sh.shift_from',
            'shift_to' => 'sh.shift_to',
            'revenue' => 'sh.revenue'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY sh.shift_closed ASC, sh.shift_closed DESC';

        return $sorting;

    }

    public static function finances_salary($field, $order){

        $access_sort = array(
            'name' => 'e.name',
            'position' => 'p.name',
            'rate' => 'g.value',
            'count' => 'count',
            'hours' => 'hours',
            'revenue' => 'revenue',
            'total' => 'total'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = '';

        return $sorting;

    }

    public static function products($field, $order){

        $access_sort = array(
            'dt1' => 'p.name',
            'dt2' => 'pc.name'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY p.name ASC';

        return $sorting;

    }

    public static function technical_cards($field, $order){

        $access_sort = array(
            'dt1' => 'p.name '.$order.', tc.subname '.$order.', tc.bulk_value '.$order.', tc.bulk_untils '.$order,
            'dt2' => 'cat.name '.$order,
            'dt3' => 'net_mass '.$order,
            'dt4' => 'cost_price '.$order,
            'dt5' => 'tc.price '.$order,
            'dt7' => 'markup '.$order,
            'dt8' => 'cashback_percent '.$order,
            'weighted' => 'tc.weighted '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];

        return $sorting;

    }

    public static function ingredients($field, $order){

        $access_sort = array(
            'dt1' => 'i.name '.$order,
            'dt2' => 'ic.name '.$order,
            'dt6' => 'count '.$order,
            'dt7' => 'price '.$order,
            'dt8' => 'sum '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];
        else
            $sorting = 'ORDER BY i.name ASC';

        return $sorting;

    }

    public static function product_categories($field, $order){

        $access_sort = array(
            'dt1' => 'pc.name '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];
        else
            $sorting = 'ORDER BY pc.name ASC';

        return $sorting;

    }

    public static function ingredient_categories($field, $order){

        $access_sort = array(
            'dt1' => 'ic.name '.$order,
            'dt2' => 'ingredient_count '.$order,
            'dt3' => 'count_num '.$order.', count_weight '.$order.', count_vol '.$order,
            'dt4' => 'stock_balance_sum '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];
        else
            $sorting = 'ORDER BY ic.name ASC';

        return $sorting;

    }

    public static function balance($field, $order){

        $access_sort = array(
            'dt1' => 'i.name',
            'dt2' => 'p.name',
            'dt4' => 'ic.name',
            'dt6' => 'count',
            'dt7' => 'pi.price',
            'dt8' => 'sum'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY i.name ASC';

        return $sorting;

    }

    public static function supplies($field, $order){

        $access_sort = array(
            'dt1' => 't.date',
            'employee' => 't.employee',
            'dt2' => 't.supplier',
            'dt12' => 't.payer',
            'dt3' => 't.point',
            'dt6' => 't.items_count',
            'dt9' => 't.comment',
            'dt10' => 't.sum',
            'dt5' => 'items',
            'dt7' => 'categories'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY t.date DESC, t.id DESC';

        return $sorting;

    }

    public static function moving($field, $order){

        $access_sort = array(
            'dt1' => 's.date '.$order,
            'dt6' => 'emp.name '.$order,
            'dt3' => 's.comment '.$order,
            'dt4' => 's.sum '.$order,
            'dt2' => 'items '.$order,
            'categories' => 'categories '.$order,
            'dt7' => 'pf.name '.$order.', p.name '.$order

        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];
        else
            $sorting = 'ORDER BY s.date DESC, s.id DESC';

        return $sorting;

    }

    public static function removal($field, $order){

        $access_sort = array(
            'dt1' => 'r.date',
            'dt2' => 'p.name',
            'dt5' => 'r.total_sum',
            'dt3' => 'products',
            'dt4' => 'categories',
            'dt7' => 'rc.name',
            'dt6' => 'e.name'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY r.date DESC';

        return $sorting;

    }

    public static function inventory($field, $order){

        $access_sort = array(
            'point' => 'p.name',
            'sum' => 'i.sum',
            'status' => 'i.status',
            'date_begin' => 'i.date_begin',
            'date_end' => 'i.date_end',
            'date_completed' => 'i.date_completed'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;

        return $sorting;

    }

    public static function suppliers($field, $order){

        $access_sort = array(
            'dt2' => 'name',
            'partner' => 'partner',
            'dt4' => 'address',
            'dt3' => 'phone',
            'dt7' => 'comment',
            'dt8' => 'supplies_count',
            'dt9' => 'supplies_sum',
            'dt6' => 'taxpayer_number',
            'USREOU' => 'USREOU'
        );

        if(isset($access_sort[$field]))
            $sorting = $access_sort[$field].' '.$order;
        else
            $sorting = 'name ASC';

        return $sorting;

    }

    public static function point($field, $order){

        $access_sort = array(
            'dt2' => 'pp.name '.$order,
            'dt3' => 'pp.address '.$order,
            'dt4' => 'sum '.$order,
            'login' => 'pp.login '.$order,
            'balance' => 'balance_count '.$order.', balance_volume '.$order.', balance_weight '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field];
        else
            $sorting = 'ORDER BY pp.name ASC';

        return $sorting;

    }

    public static function product($field, $order){

        $access_sort = array(
            'dt1' => 'i.name',
            'dt2' => 'i.bulk',
            'category' => 'cat.name',
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY i.name ASC';

        return $sorting;

    }

    public static function report_moving($field, $order){

        $access_sort = array(
            'name' => 'i.name',
            'balance_begin' => 'balance_begin',
            'average_price_begin' => 't.average_price_begin',
            'receipts' => 't.receipts',
            'costs' => 't.costs',
            'balance_end' => 'balance_end',
            'average_price_end' => 't.average_price_end'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY i.name ASC';

        return $sorting;

    }

    public static function productions($field, $order){

        $access_sort = array(
            'employee' => 'e.name',
            'point' => 'p.name',
            'comment' => 'pr.comment',
            'products_count' => 'pr.products_count',
            'sum' => 'pr.sum',
            'date' => 'pr.date'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY pr.date DESC';

        return $sorting;

    }

    public static function reports_moving($field, $order){

        $access_sort = array(
            'name' => 't.pname',
            'production_count' => 't.pcount',
            'total_price' => 't.total',
            'item' => 'i.name',
            'count' => 'count',
            'price' => 'price'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;

        return $sorting;

    }

    public static function clients($field, $order){

        $access_sort = array(
            'phone' => 'c.phone',
            'name' => 'c.name',
            'card_number' => 'c.card_number',
            'balance' => 'c.balance',
            'sale' => 'c.sale',
            'birthdate' => 'c.birthdate',
            'registration_date' => 'c.registration_date'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY c.name ASC';

        return $sorting;

    }

    public static function marketing_promotions($field, $order){

        $access_sort = array(
            'dt1' => 'p.name',
            'dt2' => 'p.description',
            'dt4' => 'p.price',
            'dt5' => 'p.created'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY p.created DESC';

        return $sorting;

    }

    public static function marketing_time_discount($field, $order){

        $access_sort = array(
            'dt1' => 'name '.$order,
            'dt3' => 'discount '.$order,
            'dt4' => 'created '.$order,
            'dt5' => 'time_from '.$order.', time_from '.$order
        );

        if(isset($access_sort[$field]))
            $sorting = $access_sort[$field];
        else
            $sorting = 'created DESC';

        return $sorting;

    }

    public static function marketing_refunds($field, $order){

        $access_sort = array(
            'employee' => 'e.name',
            'transaction.created' => 'r.refund_created',
            'created' => 'r.created',
            'transaction.point.name' => 'p.name',
            'transaction.client.name' => 'c.name',
            'transaction.client.phone' => 'c.phone',
            'transaction.sum' => 'r.sum',
            'transaction.total' => 'r.total',
            'transaction.discount' => 'r.discount'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY r.refund_created DESC';

        return $sorting;

    }

    public static function employees($field, $order){

        $access_sort = array(
            'name' => 'e.name',
            'last_enter' => 'e.last_enter',
            'email' => 'e.email',
            'position' => 'p.name'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY e.name ASC';

        return $sorting;

    }

    public static function accesses_employees($field, $order){

        $access_sort = array(
            'dt1' => 'e.name',
            'dt2' => 'e.email',
            'dt3' => 'e.pin_code',
            'dt4' => 'p.name',
            'dt5' => 'e.last_enter',
            'dt6' => 'e.employed'
        );

        if(isset($access_sort[$field]))
            $sorting = 'ORDER BY '.$access_sort[$field].' '.$order;
        else
            $sorting = 'ORDER BY e.name ASC';

        return $sorting;

    }

    public static function positions($field, $order){

        $access_sort = array(
            'dt1' => 'name',
            'access_right' => 'access_right'
        );

        if(isset($access_sort[$field]))
            $sorting = $access_sort[$field].' '.$order;
        else
            $sorting = 'name ASC';

        return $sorting;

    }

}