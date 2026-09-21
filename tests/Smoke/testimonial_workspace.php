<?php
// Integration checks use a process-local SQLite database, never the configured CMS database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
putenv('DATABASE_URL=sqlite:///:memory:');
$kernel = new class('dev', true) extends App\Kernel {
    public function getCacheDir(): string { return sys_get_temp_dir() . '/thiengia-testimonial-smoke-' . substr(sha1(__FILE__), 0, 12); }
    protected function build(Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
        parent::build($container);
        $container->addCompilerPass(new class implements Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(Symfony\Component\DependencyInjection\ContainerBuilder $container): void {
                $container->getDefinition('form.factory')->setPublic(true);
            }
        });
    }
};
$kernel->boot();
$c = $kernel->getContainer();
$em = $c->get('doctrine')->getManager();
if ($em->getConnection()->getParams()['driver'] !== 'pdo_sqlite' || !($em->getConnection()->getParams()['memory'] ?? false)) {
    throw new RuntimeException('Refusing to test outside in-memory SQLite.');
}

(new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(App\Entity\Testimonial::class)]);
$factory = $c->get('form.factory');
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$order = new App\Service\TestimonialOrderValidator();
$order->validate([2,1],[1,2],[1,2]); $order->validate([],[],[]);
foreach ([[[1,1],[1,2],InvalidArgumentException::class],[['1',2],[1,2],InvalidArgumentException::class],[[1],[1,2],InvalidArgumentException::class],[[1,99],[1,2],InvalidArgumentException::class],[null,[1,2],InvalidArgumentException::class],[[2,1],[2,1],DomainException::class]] as [$items,$expected,$exception]) {
 try { $order->validate($items,$expected,[1,2]); throw new RuntimeException('Invalid order accepted'); }
 catch(Throwable $error) { if(get_class($error)!==$exception) throw $error; }
}
$data=['name'=>'Khách hàng kiểm thử','role'=>'Chủ nhà','text'=>'Nội dung kiểm thử','rating'=>'5','avatarUrl'=>'/uploads/media/test.jpg','enable'=>'1'];
foreach ([0,1,2] as $position) {
 $item = new App\Entity\Testimonial();
 $form=$factory->create(App\Form\TestimonialType::class,$item,['csrf_protection'=>false]);
 $form->submit($data); check($form->isValid(),(string)$form->getErrors(true));
 $item->setPosition($position)->setEnable($position!==2); $em->persist($item);
}
$em->flush(); $em->clear();
$repo=$em->getRepository(App\Entity\Testimonial::class);
check(count($repo->findActiveOrdered())===2,'Active filtering');
$em->beginTransaction(); $rows=$repo->findForReorder();
$current=array_map(fn($r)=>$r->getId(),$rows);$next=array_reverse($current);
$order->validate($next,$current,$current);
foreach($rows as $i=>$row) $row->setPosition(2-$i);
$em->flush();$em->commit();$em->clear();
check(array_map(fn($r)=>$r->getId(),$repo->findAllOrdered())===$next,'Order persisted');
foreach ([['name',''],['name',str_repeat('a',256)],['text',''],['rating','6'],['avatarUrl',''],['avatarUrl','https://example.com/a.jpg'],['avatarUrl','/uploads/media/'.str_repeat('a',256)]] as [$field,$value]) {
 $invalid=$factory->create(App\Form\TestimonialType::class,new App\Entity\Testimonial(),['csrf_protection'=>false]);
 $invalid->submit(array_replace($data,[$field=>$value]));
 check(!$invalid->isValid(),'Invalid '.$field.' accepted');
}
$item=$repo->findAllOrdered()[1];$item->setName('Updated')->setEnable(false);$em->flush();
check(count($repo->findActiveOrdered())===1,'Visibility persisted');
$em->remove($item);$em->flush();check($repo->count([])===2,'Delete preserves other records');
echo "PASS isolated SQLite: forms, validation, rating, visibility, reorder persistence, stale order rejection and deletion.\n";
$kernel->shutdown();

