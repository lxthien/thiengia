<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$twig = new Twig\Environment(new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['admin/layout.html.twig'=>'{% block main %}{% endblock %}']),
    new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
]), ['autoescape'=>'html', 'strict_variables'=>true]);
$twig->addFunction(new Twig\TwigFunction('asset', fn ($path) => $path));
$twig->addFunction(new Twig\TwigFunction('path', fn ($route, $params=[]) => '/' . $route . '?' . http_build_query($params)));
$data = [
 'filters'=>['userId'=>3,'action'=>'update','entityType'=>'project','dateFrom'=>'2026-01-01','dateTo'=>'2026-12-31','search'=>'test'],
 'perPage'=>30,'filterError'=>null,'total'=>60,'pages'=>2,'currentPage'=>1,'users'=>[],
 'logs'=>[['id'=>9,'createdAt'=>new DateTimeImmutable(),'username'=>'User','action'=>'update','actionLabel'=>'Cập nhật','entityTypeLabel'=>'Công trình','entityId'=>2,'entityTitle'=>'<script>alert(1)</script>','ipAddress'=>'127.0.0.1','userAgent'=>'Test agent','details'=>'<img src=x onerror=alert(1)>']],
];
$html=$twig->render('admin/activity_log/index.html.twig',$data);
foreach (['user=3','entity_type=project','date_from=2026-01-01','date_to=2026-12-31','q=test','per_page=30','aria-controls="audit-detail-9"','class="audit-detail" hidden'] as $part) {
 if (!str_contains($html,$part)) { throw new RuntimeException('Missing '.$part); }
}
if (str_contains($html,'<script>') || str_contains($html,'<img src=x')) { throw new RuntimeException('Unsafe log output'); }
$data['logs']=[];$data['total']=0;$data['pages']=1;
$html=$twig->render('admin/activity_log/index.html.twig',$data);
if (!str_contains($html,'Không có nhật ký phù hợp')) { throw new RuntimeException('Missing empty state'); }
echo "PASS: audit details escaping, filter query names, pagination and empty state.\n";
