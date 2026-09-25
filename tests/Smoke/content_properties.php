<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
// No DB writes; purifier cache is confined to the OS temporary directory.
$listener=new App\EventListener\ContentSanitizerListener(sys_get_temp_dir().'/thiengia-properties-smoke');
$method=new ReflectionMethod($listener,'getPurifier');
$purifier=$method->invoke($listener);
$html='<figure class="image image_resized image-style-align-center" style="width:200px"><img src="/uploads/test.jpg" width="200" height="150" alt="ALT"><figcaption>Chú thích</figcaption></figure><p><a href="/updated" target="_blank" rel="nofollow noopener" title="Gợi ý"><strong>Đậm</strong> và thường</a></p><a href="javascript:alert(1)" onclick="alert(1)">Bad</a>';
$clean=$purifier->purify($html);
foreach(['width:200px','width="200"','height="150"','alt="ALT"','image-style-align-center','<figcaption>Chú thích</figcaption>','target="_blank"','nofollow','noopener','title="Gợi ý"','<strong>Đậm</strong>']as $expected)if(!str_contains($clean,$expected))throw new RuntimeException('Sanitizer lost '.$expected.' in '.$clean);
if(str_contains($clean,'javascript:')||str_contains($clean,'onclick'))throw new RuntimeException('Unsafe attributes survived');
echo "PASS image dimensions, caption, alignment, link target/rel/title survive sanitizer; unsafe URLs/events rejected.\n";
