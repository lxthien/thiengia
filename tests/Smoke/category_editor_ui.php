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
$engine = new Symfony\Bridge\Twig\Form\TwigRendererEngine(['form/layout.html.twig', 'form/fields.html.twig'], $twig);
$twig->addRuntimeLoader(new Twig\RuntimeLoader\FactoryRuntimeLoader([
    Symfony\Component\Form\FormRenderer::class => fn()=>new Symfony\Component\Form\FormRenderer($engine),
]));
$twig->addFunction(new Twig\TwigFunction('asset', fn($path)=>str_starts_with($path,'/')?$path:'/'.$path));
$twig->addFunction(new Twig\TwigFunction('get_setting', fn()=> 'Thiện Gia'));
$twig->addFunction(new Twig\TwigFunction('path', fn($route,$params=[])=>'/fixture/'.$route.'?'.http_build_query($params)));
$twig->addGlobal('app',['request'=>Symfony\Component\HttpFoundation\Request::create('/admin/page/1/edit'),'flashes'=>[]]);
$factory = Symfony\Component\Form\Forms::createFormFactory();
$fields=['parentcat','name','url','description','content','showPostRelated','enable','sortBy','cardFormat','thumbnail','pageTitle','pageDescription','pageKeyword','metaIndex','metaFollow','schemaMarkup'];
$make=function(bool $new,bool $error=false)use($factory,$fields){
    $builder=$factory->createNamedBuilder('news_category',Type\FormType::class,[]);
    foreach($fields as $name){
        $type=Type\TextType::class;$options=['label'=>$name,'required'=>false];
        if(in_array($name,['description','content','pageDescription','schemaMarkup'],true))$type=Type\TextareaType::class;
        if(in_array($name,['showPostRelated','enable','metaIndex','metaFollow'],true))$type=Type\CheckboxType::class;
        if($name==='thumbnail'){$type=Type\HiddenType::class;$options['data']='/assets/images/no-image.png';}
        if(in_array($name,['description','content'],true))$options['attr']=['class'=>'txt-ckeditor5','data-height'=>$name==='description'?'200':'600'];
        if($name==='name'){$options['data']='Danh mục thử';$options['attr']=['class'=>'sluggable'];}
        if($name==='url'){$options['data']='danh-muc-thu';$options['attr']=['class'=>'url','readonly'=>'readonly'];}
        $choices=['parentcat'=>['Danh mục gốc'=>1],'sortBy'=>['Mặc định'=>'{"createdAt":"desc"}'],'cardFormat'=>['Ảnh thường'=>'news_277_220','Ảnh dọc'=>'news_277_350']];
        if(isset($choices[$name])){$type=Type\ChoiceType::class;$options['choices']=$choices[$name];}
        $builder->add($name,$type,$options);
    }
    $builder->add('_token',Type\HiddenType::class,['data'=>'fixture-only']);
    if($new)$builder->add('saveAndCreateNew',Type\SubmitType::class);
    $form=$builder->getForm();
    if($error){$form->submit(['name'=>'Giữ tên khi lỗi','description'=>'<p>Giữ mô tả</p>','content'=>'<p>Giữ nội dung</p>','thumbnail'=>'/uploads/media/retained.jpg']);$form->get('name')->addError(new Symfony\Component\Form\FormError('Lỗi kiểm thử')); }
    return $form;
};
$render=fn($new,$error=false)=>$twig->render('admin/newscategory/'.($new?'new':'edit').'.html.twig',['category'=>['id'=>$new?null:1,'name'=>'Danh mục thử'],'form'=>$make($new,$error)->createView()]);
if(in_array('--html',$argv,true)){echo $render(!in_array('--edit',$argv,true));exit;}
foreach([true,false]as $new){
    $html=$render($new);$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xpath=new DOMXPath($dom);
    foreach($fields as $name)if($xpath->query('//*[@name="news_category['.$name.']"]')->length!==1)throw new RuntimeException('Missing/duplicated field '.$name);
    foreach(['category-general','category-content','category-display','category-image','category-seo','category-schema','mediaPicker_open','mediaPickerConfirm','mediaPicker_clear']as $id)if($xpath->query('//*[@id="'.$id.'"]')->length!==1)throw new RuntimeException('Missing section/hook '.$id);
    if(substr_count($html,'txt-ckeditor5')!==2)throw new RuntimeException('Must retain both rich text editors');
    if(!str_contains($html,'build/css/ckeditor5.css'))throw new RuntimeException('Missing CKEditor CSS');
    if(!str_contains($html,'name="news_category[_token]"'))throw new RuntimeException('Missing CSRF field');
    if(str_contains($html,'name="news_category[saveAndCreateNew]"')!==$new)throw new RuntimeException('Wrong submit buttons');
}
$html=$render(true,true);
foreach(['Chưa thể lưu danh mục.','Lỗi kiểm thử','Giữ tên khi lỗi','Giữ mô tả','Giữ nội dung','/uploads/media/retained.jpg']as $value)if(!str_contains($html,$value))throw new RuntimeException('Lost validation/submitted value '.$value);
echo "PASS: all 16 fields, both editors, media hooks, submit buttons, CSRF field, errors and submitted values retained. No database writes.\n";
