<?php

namespace App\Service;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;

class SettingsManager
{
    private $em;
    private $settings = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Load all settings into an array.
     */
    private function loadSettings(): void
    {
        if ($this->settings !== null) {
            return;
        }

        $this->settings = [];
        $settings = $this->em->getRepository(Setting::class)->findAll();
        
        $booleanKeys = ['isShowSortOnCategory', 'isShowCommentOnPost'];

        foreach ($settings as $setting) {
            $key = $setting->getSettingKey();
            $val = $setting->getSettingValue();
            
            if (in_array($key, $booleanKeys)) {
                $val = (bool) $val;
            }
            
            $this->settings[$key] = $val;
        }
    }

    /**
     * Get a setting by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $this->loadSettings();

        return $this->settings[$key] ?? $default;
    }

    /**
     * Get all settings.
     *
     * @return array
     */
    public function all(): array
    {
        $this->loadSettings();

        return $this->settings;
    }

    /**
     * Set multiple settings.
     *
     * @param array $data
     */
    public function setMany(array $data): void
    {
        $repository = $this->em->getRepository(Setting::class);

        foreach ($data as $key => $value) {
            $setting = $repository->findOneBy(['settingKey' => $key]);

            if (!$setting) {
                $setting = new Setting();
                $setting->setSettingKey($key);
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $setting->setSettingValue($value);
            $this->em->persist($setting);
        }

        $this->em->flush();
        
        // Clear local cache
        $this->settings = null;
    }
}
