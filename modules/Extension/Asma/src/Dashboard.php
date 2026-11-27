<?php

declare(strict_types=1);

namespace Yawasla\Module\Extension\Asma;

use DateTimeImmutable;
use DateTimeZone;

final class Dashboard
{
    private const EPOCH = '0622-07-19';

    private const NAMES = [
        ['اللَّهُ', 'Allah'],
        ['الرَّحْمَنُ', 'Ar-Rahman'],
        ['الرَّحِيمُ', 'Ar-Rahim'],
        ['الْمَلِكُ', 'Al-Malik'],
        ['الْقُدُّوسُ', 'Al-Quddus'],
        ['السَّلاَمُ', 'As-Salam'],
        ['الْمُؤْمِنُ', 'Al-Mu\'min'],
        ['الْمُهَيْمِنُ', 'Al-Muhaymin'],
        ['الْعَزِيزُ', 'Al-Aziz'],
        ['الْجَبَّارُ', 'Al-Jabbar'],
        ['الْمُتَكَبِّرُ', 'Al-Mutakabbir'],
        ['الْخَالِقُ', 'Al-Khaliq'],
        ['الْبَارِئُ', 'Al-Bari\''],
        ['الْمُصَوِّرُ', 'Al-Musawwir'],
        ['الْغَفَّارُ', 'Al-Ghaffar'],
        ['الْقَهَّارُ', 'Al-Qahhar'],
        ['الْوَهَّابُ', 'Al-Wahhab'],
        ['الرَّزَّاقُ', 'Ar-Razzaq'],
        ['الْفَتَّاحُ', 'Al-Fattah'],
        ['الْعَلِيمُ', 'Al-Alim'],
        ['الْقَابِضُ', 'Al-Qabid'],
        ['الْبَاسِطُ', 'Al-Basit'],
        ['الْخَافِضُ', 'Al-Khafid'],
        ['الرَّافِعُ', 'Ar-Rafi\''],
        ['الْمُعِزُّ', 'Al-Mu\'izz'],
        ['الْمُذِلُّ', 'Al-Mudhill'],
        ['السَّمِيعُ', 'As-Sami\''],
        ['الْبَصِيرُ', 'Al-Basir'],
        ['الْحَكَمُ', 'Al-Hakam'],
        ['الْعَدْلُ', 'Al-Adl'],
        ['اللَّطِيفُ', 'Al-Latif'],
        ['الْخَبِيرُ', 'Al-Khabir'],
        ['الْحَلِيمُ', 'Al-Halim'],
        ['الْعَظِيمُ', 'Al-Azim'],
        ['الْغَفُورُ', 'Al-Ghafur'],
        ['الشَّكُورُ', 'Ash-Shakur'],
        ['الْعَلِيُّ', 'Al-Aliyy'],
        ['الْكَبِيرُ', 'Al-Kabir'],
        ['الْحَفِيظُ', 'Al-Hafiz'],
        ['الْمُقِيتُ', 'Al-Muqit'],
        ['الْحَسِيبُ', 'Al-Hasib'],
        ['الْجَلِيلُ', 'Al-Jalil'],
        ['الْكَرِيمُ', 'Al-Karim'],
        ['الرَّقِيبُ', 'Ar-Raqib'],
        ['الْمُجِيبُ', 'Al-Mujib'],
        ['الْوَاسِعُ', 'Al-Wasi\''],
        ['الْحَكِيمُ', 'Al-Hakim'],
        ['الْوَدُودُ', 'Al-Wadud'],
        ['الْمَجِيدُ', 'Al-Majid'],
        ['الْبَاعِثُ', 'Al-Ba\'ith'],
        ['الشَّهِيدُ', 'Ash-Shahid'],
        ['الْحَقُّ', 'Al-Haqq'],
        ['الْوَكِيلُ', 'Al-Wakil'],
        ['الْقَوِيُّ', 'Al-Qawiyy'],
        ['الْمَتِينُ', 'Al-Matin'],
        ['الْوَلِيُّ', 'Al-Waliyy'],
        ['الْحَمِيدُ', 'Al-Hamid'],
        ['الْمُحْصِي', 'Al-Muhsi'],
        ['الْمُبْدِئُ', 'Al-Mubdi\''],
        ['الْمُعِيدُ', 'Al-Mu\'id'],
        ['الْمُحْيِي', 'Al-Muhyi'],
        ['الْمُمِيتُ', 'Al-Mumit'],
        ['الْحَىُّ', 'Al-Hayy'],
        ['الْقَيُّومُ', 'Al-Qayyum'],
        ['الْوَاجِدُ', 'Al-Wajid'],
        ['الْمَاجِدُ', 'Al-Majid'],
        ['الْوَاحِدُ', 'Al-Wahid'],
        ['الصَّمَدُ', 'As-Samad'],
        ['الْقَادِرُ', 'Al-Qadir'],
        ['الْمُقْتَدِرُ', 'Al-Muqtadir'],
        ['الْمُقَدِّمُ', 'Al-Muqaddim'],
        ['الْمُؤَخِّرُ', 'Al-Mu\'akhkhir'],
        ['الأَوَّلُ', 'Al-Awwal'],
        ['الآخِرُ', 'Al-Akhir'],
        ['الظَّاهِرُ', 'Az-Zahir'],
        ['الْبَاطِنُ', 'Al-Batin'],
        ['الْوَالِي', 'Al-Wali'],
        ['الْمُتَعَالِي', 'Al-Muta\'ali'],
        ['الْبَرُّ', 'Al-Barr'],
        ['التَّوَّابُ', 'At-Tawwab'],
        ['الْمُنْتَقِمُ', 'Al-Muntaqim'],
        ['الْعَفُوُّ', 'Al-Afuww'],
        ['الرَّءُوفُ', 'Ar-Ra\'uf'],
        ['مَالِكُ الْمُلْكِ', 'Malik al-Mulk'],
        ['ذُو الْجَلاَلِ وَالإِكْرَامِ', 'Dhul-Jalali wal-Ikram'],
        ['الْمُقْسِطُ', 'Al-Muqsit'],
        ['الْجَامِعُ', 'Al-Jami\''],
        ['الْغَنِيُّ', 'Al-Ghaniyy'],
        ['الْمُغْنِي', 'Al-Mughni'],
        ['الْمَانِعُ', 'Al-Mani\''],
        ['الضَّارُّ', 'Ad-Darr'],
        ['النَّافِعُ', 'An-Nafi\''],
        ['النُّورُ', 'An-Nur'],
        ['الْهَادِي', 'Al-Hadi'],
        ['الْبَدِيعُ', 'Al-Badi\''],
        ['الْبَاقِي', 'Al-Baqi'],
        ['الْوَارِثُ', 'Al-Warith'],
        ['الرَّشِيدُ', 'Ar-Rashid'],
        ['الصَّبُورُ', 'As-Sabur'],
    ];

    /** @return array{arabic: string, latin: string} */
    public function __invoke(): array
    {
        $utc = new DateTimeZone('UTC');
        $days = (int) (new DateTimeImmutable(self::EPOCH, $utc))->diff(new DateTimeImmutable(date('Y-m-d'), $utc))->days;
        [$arabic, $latin] = self::NAMES[$days % count(self::NAMES)];

        return ['arabic' => $arabic, 'latin' => $latin];
    }
}
