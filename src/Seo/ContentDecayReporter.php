<?php

namespace App\Seo;

use App\Entity\News;

class ContentDecayReporter
{
    public function analyze(News $post, \DateTimeInterface $now = null)
    {
        $now = $now ?: new \DateTime();
        $updatedAt = $post->getUpdatedAt() ?: $post->getCreatedAt() ?: $now;
        $ageDays = (int) $updatedAt->diff($now)->format('%a');
        $score = 0;
        $reasons = [];
        $recommendations = [];
        $viewCounts = (int) $post->getViewCounts();

        // 1. Age
        if ($ageDays >= 365) {
            $score += 35;
            $reasons[] = 'Chưa cập nhật hơn 1 năm';
        } elseif ($ageDays >= 180) {
            $score += 24;
            $reasons[] = 'Chưa cập nhật hơn 6 tháng';
        } elseif ($ageDays >= 90) {
            $score += 12;
            $recommendations[] = 'Nên rà soát lại nội dung sau 3 tháng';
        }

        // 2. SEO (Basic mock simulation for kientruc)
        $seoScore = 100;
        if (empty($post->getPageTitle())) {
            $seoScore -= 15;
            $reasons[] = 'Thiếu Meta Title';
        }
        if (empty($post->getPageDescription())) {
            $seoScore -= 15;
            $reasons[] = 'Thiếu Meta Description';
        }
        
        $wordCount = str_word_count(strip_tags((string) $post->getContents()));
        if ($wordCount < 300) {
            $seoScore -= 20;
            $score += 12;
            $reasons[] = 'Nội dung mỏng (< 300 từ)';
        } elseif ($wordCount < 600) {
            $seoScore -= 10;
            $score += 6;
            $recommendations[] = 'Có thể mở rộng nội dung';
        }

        if ($seoScore < 60) {
            $score += 32;
            $reasons[] = 'SEO score rất thấp';
        } elseif ($seoScore < 80) {
            $score += 18;
            $reasons[] = 'SEO score cần cải thiện';
        } elseif ($seoScore < 90) {
            $score += 8;
            $recommendations[] = 'SEO score còn có thể tối ưu';
        }
        
        $seoStatus = $seoScore >= 90 ? 'Tốt' : ($seoScore >= 80 ? 'Khá' : ($seoScore >= 60 ? 'Cần cải thiện' : 'Kém'));

        // 3. Views
        if ($viewCounts < 100) {
            $score += 18;
            $reasons[] = 'Lượt xem thấp';
        } elseif ($viewCounts < 500) {
            $score += 10;
            $recommendations[] = 'Lượt xem chưa tốt';
        }

        // 4. Missing Features
        if (!$post->getImages()) {
            $score += 8;
            $reasons[] = 'Thiếu ảnh đại diện';
        }

        // 5. Indexing adjust based on "robots" field for kientruc
        $robots = strtolower((string) $post->getRobots());
        $isIndexable = strpos($robots, 'noindex') === false;
        
        if (!$isIndexable) {
            $score -= 20;
            $recommendations[] = 'Đang noindex nên ít ưu tiên SEO';
        }

        if (strpos($robots, 'nofollow') !== false) {
            $score += 4;
            $recommendations[] = 'Đang nofollow';
        }

        $score = max(0, min(100, $score));

        return [
            'post' => $post,
            'decayScore' => $score,
            'decayStatus' => $this->getStatus($score),
            'ageDays' => $ageDays,
            'views' => $viewCounts,
            'seo' => [
                'score' => $seoScore,
                'status' => $seoStatus
            ],
            'reasons' => array_values(array_unique($reasons)),
            'recommendations' => array_values(array_unique($recommendations)),
            'isIndexable' => $isIndexable,
        ];
    }

    public function summarize(array $items)
    {
        $highRisk = 0;
        $needsRefresh = 0;
        $totalScore = 0;

        foreach ($items as $item) {
            $totalScore += $item['decayScore'];

            if ($item['decayScore'] >= 70) {
                $highRisk++;
            } elseif ($item['decayScore'] >= 40) {
                $needsRefresh++;
            }
        }

        return [
            'total' => count($items),
            'highRisk' => $highRisk,
            'needsRefresh' => $needsRefresh,
            'averageDecayScore' => count($items) ? round($totalScore / count($items)) : 0,
        ];
    }

    private function getStatus($score)
    {
        if ($score >= 70) {
            return 'Ưu tiên cao';
        }

        if ($score >= 40) {
            return 'Nên cập nhật';
        }

        if ($score >= 20) {
            return 'Theo dõi';
        }

        return 'Ổn';
    }
}
