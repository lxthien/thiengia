<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables'=>true,'autoescape'=>'html']);
$twig->addFunction(new Twig\TwigFunction('path',fn($route,$params=[])=>'/'.$route.'?'.http_build_query($params)));
$twig->addFunction(new Twig\TwigFunction('csrf_token',fn($id)=>'test-token'));
$twig->addFunction(new Twig\TwigFunction('asset',fn($path)=>$path));
$base=['id'=>1,'title'=>'Root <script>test</script>','url'=>'/root','isPage'=>true,'children'=>[],'parent'=>null,'status'=>App\Enum\PostStatus::Draft,'isScheduled'=>false,'scheduledAt'=>null,'publishedAt'=>null,'updatedAt'=>new DateTimeImmutable()];
$grandchild=array_replace($base,['id'=>3,'title'=>'Grandchild','parent'=>['id'=>2,'title'=>'Child']]);
$child=array_replace($base,['id'=>2,'title'=>'Child','parent'=>['id'=>1,'title'=>'Root'],'children'=>[$grandchild]]);
$post=array_replace($base,['id'=>4,'title'=>'Not a page','isPage'=>false]);
$base['children']=[$child,$post];
$html=$twig->render('admin/page/_tree_row.html.twig',['page'=>$base,'level'=>0,'render_children'=>true,'seen'=>[]]);
if (!str_contains($html,'Chưa có ảnh')) throw new RuntimeException('Missing empty thumbnail state');
$imagePage=array_replace($base,['images'=>'/uploads/media/example.jpg','children'=>[]]);
$imageHtml=$twig->render('admin/page/_tree_row.html.twig',['page'=>$imagePage,'level'=>0,'seen'=>[]]);
if (!str_contains($imageHtml,'src="/uploads/media/example.jpg"') || !str_contains($imageHtml,'data-page-image-fallback')) throw new RuntimeException('Missing thumbnail or fallback');
foreach (['--page-level:0','--page-level:1','--page-level:2','Cấp 3','Thuộc:','value="3"'] as $expected) {
 if (!str_contains($html,$expected)) throw new RuntimeException('Missing '.$expected);
}
if (str_contains($html,'<script>') || str_contains($html,'Not a page')) throw new RuntimeException('Unsafe or non-page child rendered');
if (substr_count($html,'data-page-delete')!==1) throw new RuntimeException('Only leaf can be deleted');
$html=$twig->render('admin/page/_tree_row.html.twig',['page'=>$child,'level'=>1,'render_children'=>false,'seen'=>[]]);
if (str_contains($html,'Grandchild')) throw new RuntimeException('Search must not duplicate descendants');
$html=$twig->render('admin/page/_tree_row.html.twig',['page'=>$base,'level'=>2,'render_children'=>true,'seen'=>[1]]);
if (str_contains($html,'page-row')) throw new RuntimeException('Cycle guard failed');
echo "PASS page hierarchy: three levels, parent links, page-only children, leaf deletion, search, escaping, cycle guard.\n";
