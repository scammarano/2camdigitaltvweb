<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/github_updater.php';
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException('TEST FAIL: '.$message);}
check(gh_api_url('')===DS_GITHUB_API,'api root');
check(gh_api_url('/releases?per_page=30')===DS_GITHUB_API.'/releases?per_page=30','releases');
try{gh_api_url('/releases/../admin');throw new RuntimeException('unsafe path accepted');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'no permitida'),'url path');}
check(gh_asset_allowed_redirect('https://release-assets.githubusercontent.com/download/ab'),'redirect CDN valid');
check(!gh_asset_allowed_redirect('http://github.com/foo'),'no HTTP');
check(!gh_asset_allowed_redirect('https://release-assets.githubusercontent.com.evil.net/foo'),'no phishing');
check(!gh_asset_allowed_redirect('https://token:pass@github.com/foo'),'no basic auth');
$release=['id'=>123,'tag_name'=>'web-v0.4.19','draft'=>false,'prerelease'=>false,'assets'=>[
    ['id'=>456,'name'=>'SIGNAGE_UPDATE_v0.4.19_WEB_RSA.zip','size'=>12345,'digest'=>'sha256:'.str_repeat('a',64)],
], 'published_at'=>'2026-10-15T12:00:00Z'];
$results=gh_filter_releases([$release], '0.4.18');
check(count($results)===1 && $results[0]['asset_id']===456 && $results[0]['version']==='0.4.19','release valid');
check(gh_filter_releases([$release], '0.4.19')===[],'reject equal release');
check(gh_filter_releases([array_merge($release,['prerelease'=>true])], '0.4.18')===[],'reject prerelease');
check(gh_filter_releases([array_merge($release,['assets'=>[['id'=>456,'name'=>'code.zip','size'=>1500]]])], '0.4.18')===[],'reject unsigned nonmatching name');
check(gh_filter_releases([array_merge($release,['tag_name'=>'web-v0.4.19-evil'])], '0.4.18')===[],'reject tag mismatch');
$fakeToken=str_repeat('a',40);
gh_token_save($fakeToken);
check(gh_token_read()===$fakeToken,'token store/read');
check((fileperms(gh_token_path())&077)===0,'token mode private');
gh_token_remove();
check(gh_token_read()===null,'token deleted');
echo "PASS GitHub updater smoke tests\n";
