<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$root=dirname(__DIR__,2);
$loader=new Twig\Loader\ChainLoader([new Twig\Loader\ArrayLoader([
'fixture.html.twig'=>"{% extends 'admin/layout.html.twig' %}{% block main %}<main style=\"padding:24px\"><h1>Quản trị nội dung</h1><p>Dữ liệu kiểm thử sidebar.</p></main>{% endblock %}",
'admin/layout/header.html.twig'=>'<header class="header"><button type="button" id="menuToggle" data-sidebar-managed class="menutoggle" aria-controls="left-panel" aria-label="Thu nhỏ thanh điều hướng"><i class="fa fa-bars" aria-hidden="true"></i></button></header>',
'admin/common/_flash_messages.html.twig'=>'',
]),new Twig\Loader\FilesystemLoader($root.'/templates')]);
$twig=new Twig\Environment($loader,['strict_variables'=>true,'autoescape'=>'html']);
$twig->addFunction(new Twig\TwigFunction('path',fn($route,$params=[])=>'/fixture/'.$route.'?'.http_build_query($params)));
$twig->addFunction(new Twig\TwigFunction('asset',fn($path)=>'/'.ltrim($path,'/')));
$twig->addFunction(new Twig\TwigFunction('get_setting',fn($key)=>'THIỆN GIA'));
$role='ROLE_ADMIN';
$twig->addFunction(new Twig\TwigFunction('is_granted',function($requested)use(&$role){return $role==='ROLE_ADMIN'||$role===$requested;}));
$twig->addFilter(new Twig\TwigFilter('trans',fn($s)=>$s));
$render=function($route,$cookie='0')use($twig){
 $request=Symfony\Component\HttpFoundation\Request::create('/admin/');$request->attributes->set('_route',$route);$request->attributes->set('id',42);$request->cookies->set('kientruc_admin_sidebar_open',$cookie);
 $twig->addGlobal('app',['request'=>$request,'user'=>['id'=>42],'flashes'=>[]]);
 return $twig->render('fixture.html.twig');
};
if(in_array('--html',$argv,true)){echo $render($argv[2]??'admin_newscategory_new',$argv[3]??'0');exit;}
foreach(['admin_newscategory_new'=>'admin_newscategory_index','admin_page_edit'=>'admin_page_index','admin_news_new'=>'admin_news_new','admin_user_edit'=>'admin_user_edit','admin_tag_edit'=>'admin_tag_index']as $route=>$target){
 $html=$render($route);$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);$active=$xp->query('//a[@aria-current="page"]');
 if($active->length!==1||!str_contains($active->item(0)->getAttribute('href'),$target))throw new RuntimeException('Wrong active item: '.$route);
 if(in_array($route,['admin_newscategory_new','admin_tag_edit'])&&!preg_match('/aria-expanded="true" aria-controls="nav-news"/',$html))throw new RuntimeException('Active group not open');
}
$html=$render('admin_dashboard_index');
$expected=['admin_dashboard_index','admin_news_index','admin_news_new','admin_newscategory_index','admin_tag_index','admin_page_index','admin_page_new','admin_menu_index','admin_comment_index','admin_contact_index','admin_newsletter_index','admin_media_index','admin_homepage_section_index','admin_banner_index','admin_service_index','admin_project_index','admin_gallery_album_index','admin_testimonial_index','admin_user_index','admin_user_new','admin_user_edit','admin_health_index','admin_content_decay_index','admin_activity_log_index','admin_settings_global','admin_settings_construction_cost','admin_redirect_index'];
foreach($expected as $route)if(!str_contains($html,'/fixture/'.$route.'?'))throw new RuntimeException('Missing route '.$route);
$role='ROLE_EDITOR';$html=$render('admin_page_edit');
foreach(['admin_menu_index','admin_contact_index','admin_newsletter_index','admin_user_index','admin_health_index','admin_activity_log_index','admin_settings_global','admin_redirect_index']as $route)if(str_contains($html,'/fixture/'.$route.'?'))throw new RuntimeException('Admin link leaked '.$route);
foreach(['admin_page_index','admin_media_index','admin_user_edit']as $route)if(!str_contains($html,'/fixture/'.$route.'?'))throw new RuntimeException('Editor link missing '.$route);
echo "PASS all 27 original routes, active routes/groups, editor/admin permissions. No database writes.\n";
