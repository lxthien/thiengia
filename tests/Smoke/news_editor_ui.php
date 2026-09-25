<?php
// Isolated Twig + Symfony Form test. No database connection or content writes.
require dirname(__DIR__, 2).'/vendor/autoload.php';
use Symfony\Component\Form\Extension\Core\Type as Type;
$root = dirname(__DIR__, 2);
$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader([
        'admin/layout/sidebar.html.twig' => '<aside id="left-panel" class="left-panel"><nav class="navbar navbar-expand-sm navbar-default"><strong style="color:white">THIỆN GIA</strong></nav></aside>',
        'admin/layout/header.html.twig' => '<header id="header" class="header">Quản trị nội dung — dữ liệu kiểm thử</header>',
    ]),
    new Twig\Loader\FilesystemLoader([$root.'/templates', $root.'/vendor/symfony/twig-bridge/Resources/views/Form']),
]);
$twig = new Twig\Environment($loader, ['strict_variables'=>true, 'autoescape'=>'html']);
$translator = new Symfony\Component\Translation\Translator('vi');
$translator->addLoader('xlf', new Symfony\Component\Translation\Loader\XliffFileLoader());
$translator->addResource('xlf', $root.'/translations/messages.vi.xlf', 'vi');
$twig->addExtension(new Symfony\Bridge\Twig\Extension\TranslationExtension($translator));
$twig->addExtension(new Symfony\Bridge\Twig\Extension\FormExtension());
$engine = new Symfony\Bridge\Twig\Form\TwigRendererEngine(['form/layout.html.twig', 'form/fields.html.twig', 'form/hierarchical_category_theme.html.twig'], $twig);
$twig->addRuntimeLoader(new Twig\RuntimeLoader\FactoryRuntimeLoader([
    Symfony\Component\Form\FormRenderer::class => fn()=>new Symfony\Component\Form\FormRenderer($engine),
]));
$twig->addFunction(new Twig\TwigFunction('asset', fn($path)=>str_starts_with($path,'/')?$path:'/'.$path));
$twig->addFunction(new Twig\TwigFunction('get_setting', fn()=> 'Thiện Gia'));
$twig->addFunction(new Twig\TwigFunction('path', fn($route,$params=[])=>'/fixture/'.$route.'?'.http_build_query($params)));
$twig->addGlobal('app',['request'=>Symfony\Component\HttpFoundation\Request::create('/admin/news/1/edit'),'flashes'=>[]]);
$factory = Symfony\Component\Form\Forms::createFormFactory();
$fields = ['title','url','status','scheduledAt','images','description','contents','ordering','categoryPrimary','category','pageTitle','pageDescription','pageKeyword','metaIndex','metaFollow','postType','relatedNews','contactHotline','schemaMarkup','template','note'];
$make = function(bool $admin, bool $new, bool $error=false) use($factory,$fields) {
    $form=$factory->createNamedBuilder('news', Type\FormType::class, ['id'=>$new?null:1]);
    foreach($fields as $name) {
        if(!$admin && in_array($name,['postType','schemaMarkup'],true))continue;
        $options=['required'=>false,'label'=>$name]; $type=Type\TextType::class;
        if(in_array($name,['description','contents','pageDescription','schemaMarkup','note','contactHotline'],true))$type=Type\TextareaType::class;
        if(in_array($name,['images','categoryPrimary'],true))$type=Type\HiddenType::class;
        if(in_array($name,['metaIndex','metaFollow'],true))$type=Type\CheckboxType::class;
        if($name==='title')$options+=['data'=>'Bài kiểm thử <script>','required'=>true,'attr'=>['class'=>'sluggable']];
        if($name==='url')$options+=['data'=>'bai-kiem-thu','attr'=>['class'=>'url','readonly'=>'readonly']];
        if($name==='contents')$options+=['data'=>'<p>Nội dung được giữ nguyên.</p>','attr'=>['class'=>'txt-ckeditor5','data-height'=>'500']];
        if($name==='scheduledAt'){$type=Type\DateTimeType::class;$options+=['widget'=>'single_text','attr'=>['class'=>'js-scheduled-at']];}
        if($name==='images')$options['data']='/assets/images/no-image.png';
        if($name==='categoryPrimary')$options['data']='2';
        $choices=['status'=>['Bản nháp'=>'draft','Đặt lịch'=>'scheduled','Xuất bản'=>'published'], 'template'=>['Mặc định'=>'2_columns','Landing page'=>'1_column'], 'postType'=>['Post'=>'post','Page'=>'page'], 'category'=>['Thiết kế'=>1,'Nhà phố'=>2,'Biệt thự'=>3]];
        if(isset($choices[$name])){$type=Type\ChoiceType::class;$options['choices']=$choices[$name];}
        if($name==='category')$options+=['multiple'=>true,'expanded'=>true,'data'=>[1,2],'choice_attr'=>fn($v)=>['data-category-id'=>$v,'data-parent-id'=>$v===1?null:1,'class'=>'category-checkbox']];
        $form->add($name,$type,$options);
    }
    $form->add('_token',Type\HiddenType::class,['data'=>'fixture-only']);
    $form->add('save',Type\SubmitType::class);
    if($new)$form->add('saveAndCreateNew',Type\SubmitType::class);
    $result=$form->getForm();
    if($error){$result->submit(['title'=>'Giữ tiêu đề khi lỗi','contents'=>'<p>Bản đang sửa</p>','category'=>['3']]);$result->get('title')->addError(new Symfony\Component\Form\FormError('Lỗi kiểm thử tiêu đề'));}
    return $result;
};
$render = function($admin,$new,$error=false)use($make,$twig){return $twig->render('admin/news/'.($new?'new':'edit').'.html.twig',['news'=>['id'=>$new?null:1,'title'=>'Bài kiểm thử','url'=>'bai-kiem-thu'],'form'=>$make($admin,$new,$error)->createView()]);};
if(in_array('--html',$argv,true)){echo $render(true,in_array('--new',$argv,true));exit;}
foreach([true,false] as $admin)foreach([true,false] as $new) {
    $html=$render($admin,$new);$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xpath=new DOMXPath($dom);
    foreach($fields as $name){
        if($name==='category')continue;
        $expected=(!$admin&&in_array($name,['postType','schemaMarkup'],true))?0:1;
        if($xpath->query('//*[@name="news['.$name.']"]')->length!==$expected)throw new RuntimeException('Missing/duplicate field '.$name);
    }
    if($xpath->query('//input[@name="news[category][]"]')->length!==3)throw new RuntimeException('Category tree must render every category once');
    if($xpath->query('//input[@name="news[category][]"][@checked]')->length!==2)throw new RuntimeException('Selected categories lost');
    foreach(['news-general','news-image','news-content','news-category','news-seo','news-publishing','news-advanced'] as $id)if($xpath->query('//*[@id="'.$id.'"]')->length!==1)throw new RuntimeException('Missing section '.$id);
    foreach(['data-cms-block-toolbar','data-seo-checker','data-media-picker-open','data-category-filter','build/js/news-editor.js','build/css/page-editor.css','name="news[_token]"','name="news[save]"'] as $hook)if(!str_contains($html,$hook))throw new RuntimeException('Missing integration '.$hook);
    if(str_contains($html,'Bài kiểm thử <script>'))throw new RuntimeException('Title must be escaped');
    if(str_contains($html,'name="news[saveAndCreateNew]"')!==$new)throw new RuntimeException('Wrong submit buttons');
    if(str_contains($html,'preview_id')===$new)throw new RuntimeException('Preview link only on edit');
}
$html=$render(true,false,true);
foreach(['Chưa thể lưu bài viết.','Lỗi kiểm thử tiêu đề','Giữ tiêu đề khi lỗi','Bản đang sửa'] as $value)if(!str_contains($html,$value))throw new RuntimeException('Lost validation/input: '.$value);
echo "PASS: 21 news fields, category tree + primary, edit/new buttons, role-gated fields, CSRF field, editor/media/SEO hooks, error and submitted-value preservation. No database writes.\n";
