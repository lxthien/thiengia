<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables'=>true,'autoescape'=>'html']);
$twig->addFunction(new Twig\TwigFunction('path',fn($route,$params=[])=>'/'.$route.'?'.http_build_query($params)));
$twig->addFunction(new Twig\TwigFunction('csrf_token',fn($id)=>'test-token'));
$twig->addFunction(new Twig\TwigFunction('asset',fn($path)=>$path));
$now=new DateTimeImmutable();
$base=['id'=>1,'name'=>'Root <script>test</script>','url'=>'root','thumbnail'=>null,'children'=>[],'parentcat'=>null,'enable'=>true,'metaIndex'=>true,'createdAt'=>$now,'updatedAt'=>$now];
$grandchild=array_replace($base,['id'=>3,'name'=>'Grandchild','enable'=>false,'metaIndex'=>false,'parentcat'=>['id'=>2,'name'=>'Child']]);
$child=array_replace($base,['id'=>2,'name'=>'Child','parentcat'=>['id'=>1,'name'=>'Root'],'children'=>[$grandchild]]);
$base['children']=[$child];
$counts=[1=>0,2=>5];
$row=fn($category,$level,$renderChildren,$seen=[])=>$twig->render('admin/newscategory/_tree_row.html.twig',['category'=>$category,'level'=>$level,'render_children'=>$renderChildren,'news_counts'=>$counts,'seen'=>$seen]);
$html=$row($base,0,true);
foreach (['--category-level:0','--category-level:1','--category-level:2','Cấp 3','Thuộc:','Chưa có ảnh','Đang ẩn','noindex','5 bài','0 bài','data-news="0"','categoryId=2'] as $expected) {
 if (!str_contains($html,$expected)) throw new RuntimeException('Missing '.$expected);
}
if (str_contains($html,'<script>')) throw new RuntimeException('Unescaped name');
if (substr_count($html,'data-category-delete')!==1) throw new RuntimeException('Only leaf categories can be deleted');
$imageHtml=$row(array_replace($base,['thumbnail'=>'/uploads/media/example.jpg','children'=>[]]),0,true);
if (!str_contains($imageHtml,'src="/uploads/media/example.jpg"') || !str_contains($imageHtml,'data-category-image-fallback')) throw new RuntimeException('Missing thumbnail or fallback');
if (str_contains($row($child,1,false),'Grandchild')) throw new RuntimeException('Filtered list must not duplicate descendants');
if (str_contains($row($base,2,true,[1]),'category-row')) throw new RuntimeException('Cycle guard failed');
echo "PASS category hierarchy: three levels, parent links, status/noindex, news counts, leaf deletion, filtering, escaping, cycle guard.\n";
