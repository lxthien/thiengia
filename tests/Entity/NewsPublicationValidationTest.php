<?php

namespace App\Tests\Entity;

use App\Entity\News;
use App\Enum\PostStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationBuilderInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class NewsPublicationValidationTest extends TestCase
{
    public function testScheduledContentRequiresAFuturePublicationTime(): void
    {
        $news = new News();
        $news->setStatus(PostStatus::Scheduled);

        $missingScheduleContext = $this->expectScheduleViolation();
        $news->validatePublicationSchedule($missingScheduleContext);

        $news->setScheduledAt(new \DateTimeImmutable('+1 hour'));
        $futureScheduleContext = $this->createMock(ExecutionContextInterface::class);
        $futureScheduleContext->expects(self::never())->method('buildViolation');
        $news->validatePublicationSchedule($futureScheduleContext);

        $news->setScheduledAt(new \DateTimeImmutable('-1 minute'));
        $pastScheduleContext = $this->expectScheduleViolation();
        $news->validatePublicationSchedule($pastScheduleContext);
    }

    private function expectScheduleViolation(): ExecutionContextInterface
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('atPath')->with('scheduledAt')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())->method('buildViolation')->willReturn($builder);

        return $context;
    }
}
