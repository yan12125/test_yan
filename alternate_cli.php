<?php
    $root = 'http://localhost/fb/test_yan_dev/';
    echo "Retrieve users' data from server...\n";
	$users_json = json_decode(file_get_contents($root.'users.php?action=list_users'), true);
    echo "Data transmission completed. Analyzing...\n";
    $users = array();
	foreach($users_json as $user)
	{
		if($user['status'] === 'started')
		{
			$users[$user['uid']] = $user;
			$users[$user['uid']]['timeout'] = rand()%10; // to prevent all start at the same time
		}
	}
	for(;;)
    {
        foreach($users as $uid => $user)
        {
            if($user['timeout']<=0)
            {
                $ch = curl_init($root.'post.php');
                $query_string = http_build_query(array(
                    "interval_max" => $user['interval_max'], 
                    "interval_min" => $user['interval_min'], 
                    "titles" => $user['titles'], 
                    "access_token" => $user['access_token'], 
                    "uid" => $uid 
                ));
                curl_setopt_array($ch, array(
                    CURLOPT_BINARYTRANSFER => true, 
                    CURLOPT_RETURNTRANSFER => true, 
                    CURLOPT_POSTFIELDS => $query_string, 
                    CURLOPT_POST => true
                ));
                $result = curl_exec($ch);
                $arr_result = null;
                $arr_result = json_decode($result, true);
                if($arr_result !== null)
                {
                    if(isset($arr_result['error']))
                    {
                        print_r($arr_result);
                        $user['timeout'] = 2*86400;
                    }
                    else
                    {
                        echo $user['name']." posted.\n";
                        $users[$uid]['timeout'] = $arr_result['next_wait_time'];
                    }
                }
            }
            else
            {
                $users[$uid]['timeout']--;
            }
        }
        sleep(1);
    }
?>
