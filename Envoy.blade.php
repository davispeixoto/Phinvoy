<?php
$ret = 0;

exec("phing", $data, $ret);

if ($ret != 0) {
	exit(1);
}

$servers_list = [];
$servers_deploy = [];
$app = "";
$branch = "";
$base_path = "";
$code_path = "";
$releases_path = "";
$repo_link = "";
$current_release = "";
$shared_path = "";
$app_path = "";
$data = array();
$ret = 0;
$i = 1;

exec("php artisan deploy:getdata $id" , $data , $ret);

if ($ret != 0) {
	exit(1);
}

foreach ($data as $key => $row) {
	$aux = explode(':', $row);
	switch ($aux[0]) {
		case "server" :
			$srv_alias = 'web-' . $i;
			$servers_list[$srv_alias] = "ec2-user@" . $aux[1];
			$servers_deploy[] = $srv_alias;
			$i++;
			break;
		case "app" :
			$app = $aux[1];
			break;
		case "branch" :
			$branch = $aux[1];
			break;
		case "base_path" :
			$base_path = $aux[1];
			break;
		case "code_path" :
			$code_path = $aux[1];
			break;
		case "releases_path" :
			$releases_path = $aux[1];
			break;
		case "repo_link" :
			$repo_link = $aux[1];
			break;
		case "app_path" :
			$app_path = $aux[1];
			break;
		case "shared_path" :
			$shared_path = $aux[1];
			break;
	}
}

$now = date('YmdHis');
$current_release = $releases_path . $now . '/';
?>

@servers($servers_list)


@macro('deploy')
	deploy_code
	change_permissions
	link_current
	buildlinks
    purge
@endmacro


@macro('rollback')
    link_back
    purge_last_release
@endmacro


@macro('cleanup')
    cleancaches
@endmacro


@task('deploy_code', ['on' => $servers_deploy, 'parallel' => true])
	cd {{ $shared_path }};
	git reset --hard;
	git pull;
	cp -RPp {{ $shared_path }} {{ $current_release }};
	cd {{ $current_release }};
	git checkout -t origin/{{ $branch }};
@endtask


@task('change_permissions', ['on' => $servers_deploy, 'parallel' => true])
	sudo find {{ $current_release }} -type d -exec chown ec2-user:apache {} +; 
	sudo find {{ $current_release }} -type f -exec chown ec2-user:apache {} +;
	sudo find {{ $current_release }} -type d -exec chmod 775 {} +;
	sudo find {{ $current_release }} -type f -exec chmod 664 {} +;
@endtask


@task('purge', ['on' => $servers_deploy, 'parallel' => true])
	find {{ $releases_path }} -maxdepth 1 -type d -name "20*" | sort | head -n -5 | xargs -I % rm -rf %;
@endtask


@task('link_current', ['on' => $servers_deploy, 'parallel' => true])
	sudo rm -f {{ $code_path }}current;
	sudo ln -sfn {{ $current_release }} {{ $code_path }}current;
@endtask


@task('buildlinks', ['on' => $servers_deploy, 'parallel' => true])
	rm -f {{ $app_path }}application/views;
	rm -f {{ $app_path }}application;
	rm -f {{ $app_path }}public;
	rm -f {{ $app_path }}system;
	
	ln -sfn {{ $base_path }}Foo/application {{ $app_path }}application;
	ln -sfn {{ $base_path }}Foo/public {{ $app_path }}public;
	ln -sfn {{ $base_path }}Foo/system {{ $app_path }}system;
	ln -sfn {{ $base_path }}Bar/views {{ $app_path }}application/views;
@endtask


@task('link_back' , ['on' => $servers_deploy, 'parallel' => true])
	find {{ $releases_path }} -maxdepth 1 -type d -name "20*" | sort | tail -n +3 | head -n 1 | xargs -I % ln -sf % {{ $code_path }}current;
@endtask


@task('purge_last_release' , ['on' => $servers_deploy, 'parallel' => true])
	find {{ $releases_path }} -maxdepth 1 -type d -name "20*" | sort | tail -n +2 | xargs rm -rf ;
@endtask


@task('cleancaches', ['on' => $servers_deploy, 'parallel' => true])
	rm -rf {{ $app_path }}storage/files/*
	rm -rf {{ $app_path }}storage/tmp/*
	rm -rf {{ $app_path }}storage/logs/*
@endtask