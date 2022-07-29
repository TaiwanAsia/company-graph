<?php

include(__DIR__ . '/../init.inc.php');

$get_online_data_time = function(){
    $doc = new DOMDocument;
    @$doc->loadHTML(file_get_contents("http://ronnywang-twcompany.s3-website-ap-northeast-1.amazonaws.com/index.html"));

    $time = null;
    foreach ($doc->getElementsByTagName('tr') as $tr_dom) {
        $td_doms = $tr_dom->getElementsByTagName('td');
        if ($td_doms->item(0)->nodeValue == 'files/') {
            $time = strtotime($td_doms->item(1)->nodeValue);
        }
    }
    return $time;
};

$old_data_time = KeyValue::get('data_updated_at');

$new_data_time = $get_online_data_time();

//echo "old:  $old_data_time\nnew:  $new_data_time";
//exit;

//if (is_null($new_data_time) or $new_data_time <= $old_data_time) {
//    error_log("skip");
//    exit;
//}



//mkdir("C:/MAMP/htdocs/company-graph/tmp/company-graph-data", 0777, true);
//for ($i = 0; $i < 10; $i ++) {
//    exec("wget -O C:/MAMP/htdocs/company-graph/tmp/company-graph-data/{$i}.gz http://ronnywang-twcompany.s3-website-ap-northeast-1.amazonaws.com/files/{$i}0000000.jsonl.gz");
//}


$count = 0;

$output = fopen('C:/MAMP/htdocs/company-graph/tmp/company-graph-data/graph.csv', 'w');
for ($i = 0; $i < 10; $i ++) {
    $filename = "C:/MAMP/htdocs/company-graph/tmp/company-graph-data/{$i}.gz";
    echo "\n\n  -----  fileName ----- : $filename";
    $fp = gzopen($filename, 'r');



    if ($count > 5) {
//            exit;
//        break;
    }


    while ($line = fgets($fp)) {

        if ($count > 5) {
//            exit;
//            break;
        }


//        echo "\n\nCount : $count";

        $obj = json_decode($line);

//        echo "\n\n";
//        print_r($obj);

        $showed = array();
        if (!$obj or !property_exists($obj, '董監事名單')) {
            continue;
        }

        $main_id = $obj->id;
        echo "\n";
        echo "company_id: $main_id";

        foreach ($obj->{'董監事名單'} as $index => $record) {




//            echo "序號: $record->序號";


            if (!$record->{'所代表法人'}) {
                continue;
            }

            echo "\n\n";
            print_r($record);

            #  問題出在第一筆就算是台灣公司 id卻都沒有進   AND   [有時]最後一筆id也沒有進 get to know why 'sometimes'
            if ($record->{'所代表法人'}[0]) {

                echo "\n$main_id -> id";
                $board_type = 'id';
                echo "\n所代表法人[0]: ".$record->{'所代表法人'}[0];
                $value = $record->{'所代表法人'}[0];

            } else {
                # 非台灣公司  Case 1: 外商   Case 2: 政府
                echo "\n$main_id -> name";
                $board_type = 'name';
                $value = $record->{'所代表法人'}[1];
                $value = json_encode($value);
            }

            $value = ltrim($value,'0');

            echo "\nvalue: $value -> ".gettype($value);

            if (!$record->{'出資額'}) {
                echo "\n$main_id -> [NO 2]";
                continue;
            }
            $id = $main_id . $board_type . $value;
            echo "\nunique_id: $id";
            if (array_key_exists($id, $showed)) {
                continue;
            }
            $showed[$id] = true;


            fputcsv($output, array(
                $main_id,
                $board_type,
                $value,
                '',
                str_replace(',', '', $record->{'出資額'}),
            ));

            $count ++;


        }

    }
    fclose($fp);
}
fclose($output);

//exit;

$fp = fopen('C:/MAMP/htdocs/company-graph/tmp/company-graph-data/graph.csv', 'r');

$db = CompanyGraph::getDb();

try {
    echo "\n\n DROP TABLE company_graph_tmp";
    $sql = "DROP TABLE company_graph_tmp";
    $db->query($sql);
} catch (Exception $e) {
}

echo "\n\n CREATE TABLE company_graph_tmp LIKE company_graph";
$sql = "CREATE TABLE company_graph_tmp LIKE company_graph";
$db->query($sql);

$terms = array();
while ($row = fgetcsv($fp)) {
    list($company_id, $board_type, $board_value,, $amount) = $row;

    echo "\ncompany_id: $company_id";

    $terms[] = sprintf("(%d,%d,%d,'',%d)",
        intval($company_id),
        ('name' == $board_type) ? 1 : 0,
        ('name' == $board_type) ? crc32($board_value) : $board_value,
        intval($amount)
    );

    if (count($terms) >= 5000) {
        echo "\n\nInserting into company_graph_tmp...";
        $sql = "INSERT INTO company_graph_tmp (company_id, board_type, board_id, board_name, amount) VALUES " . implode(',', $terms);
        $db->query($sql);
        $terms = array();
    }
}
fclose($fp);

if (count($terms)) {
    echo "\n\nFinal Inserting into company_graph_tmp...";
    $sql = "INSERT INTO company_graph_tmp (company_id, board_type, board_id, board_name, amount) VALUES " . implode(',', $terms);
    $db->query($sql);
    $terms = array();
}
try {
    $db->query("DROP TABLE company_graph_old");
} catch (Exception $e) {
}

// 如果同一個月的話，就直接砍掉舊的，表示可能是抓到一半
if (!$old_data_time or date('Ym', $old_data_time) == date('Ym', $new_data_time)) {
    $db->query("RENAME TABLE company_graph TO company_graph_old");
    $db->query("RENAME TABLE company_graph_tmp TO company_graph");
} else {
    $db->query("RENAME TABLE company_graph TO company_graph_" . date('Ym', $old_data_time));
    $db->query("RENAME TABLE company_graph_tmp TO company_graph");
}
KeyValue::set('data_updated_at', $new_data_time);

