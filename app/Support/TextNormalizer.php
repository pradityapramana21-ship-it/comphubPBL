<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisasi teks sebelum dicocokkan dengan aturan moderasi.
 *
 * Tanpa normalisasi, penyaring kata jadi tidak berguna: orang menulis
 * "Gr4t1s", "G R A T I S", atau "gratis!!!" untuk lolos. Tujuan kelas ini
 * adalah mengembalikan semua cara menghindari ke bentuk yang sama,
 * sehingga satu daftar kata cukup menutup semuanya.
 *
 * Normalisasi TIDAK mengubah isi yang disimpan di database. Yang dinormalisasi
 * hanya salinan yang dipakai untuk pencocokan — kalau tidak, pencarian
 * pengguna ikut kehilangan tanda baca dan huruf besar.
 *
 * ADA DUA BENTUK, dan pemisahan ini penting:
 *
 *   normalise()            huruf kecil, aksen, spasi, huruf berulang.
 *                          TIDAK menyentuh leetspeak.
 *
 *   normaliseAggressive()  bentuk di atas PLUS leetspeak, untuk menangkap
 *                          "gr4t1s".
 *
 * Pemisahan ini bukan sekadar formal: leetspeak yang diterapkan ke semua teks
 * akan merusak angka. "FEMC 2026" menjadi "2o26", dan setiap tahun yang
 * mengandung 0 atau 8 ikut berubah — sehingga pencocokan duplikasi dan
 * pemeriksaan kualitas isi ikut salah.
 */
final class TextNormalizer
{
    /**
     * Pemetaan leetspeak ke huruf biasa.
     *
     * @var array<string, string>
     */
    private const LEET = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '9' => 'g', '@' => 'a', '$' => 's',
        '!' => 'i', '|' => 'i', '+' => 't',
    ];

    /**
     * Aksen ke huruf ASCII. Bahasa Indonesia memakai huruf latin dasar,
     * tapi kegiatan sering disalin dari luar negeri dan memakai aksen.
     *
     * @var array<string, string>
     */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
    ];

    /**
     * Bentuk konservatif: aman untuk teks yang isinya berarti
     * (judul, deskripsi, tahun, nomor).
     */
    public function normalise(string $text): string
    {
        return $this->condense(mb_strtolower($text, 'UTF-8'), 3);
    }

    /**
     * Bentuk agresif: menambah leetspeak, hanya untuk pencocokan kata
     * terlarang dan pola yang mencari cara kecil untuk lolos.
     */
    public function normaliseAggressive(string $text): string
    {
        return $this->condense(strtr(mb_strtolower($text, 'UTF-8'), self::LEET), 2);
    }

    /**
     * Apakah sebuah istilah muncul di teks, dalam salah satu dari dua
     * bentuk normalisasi.
     *
     * Ini cara paling aman untuk mencocokkan kata terlarang: frasa target
     * tidak pernah diubah oleh pemanggil, sementara teks yang dibaca
     * diperiksa dalam kedua bentuk.
     */
    public function contains(string $haystack, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '') {
            return false;
        }

        return str_contains($this->normaliseAggressive($haystack), $this->normaliseAggressive($needle))
            || str_contains($this->normalise($haystack), $this->normalise($needle));
    }

    /**
     * Berapa kali sebuah frasa muncul, dihitung tanpa tumpang tindih.
     */
    public function countPhrase(string $haystack, string $phrase): int
    {
        $needle = $this->normaliseAggressive($phrase);

        if ($needle === '') {
            return 0;
        }

        $haystack = $this->normaliseAggressive($haystack);

        return preg_match_all('/' . preg_quote($needle, '/') . '/u', $haystack) ?: 0;
    }

    /**
     * Daftar token kata dari sebuah teks.
     *
     * @return list<string>
     */
    public function tokens(string $text, bool $aggressive = false): array
    {
        $normalised = $aggressive
            ? $this->normaliseAggressive($text)
            : $this->normalise($text);

        if ($normalised === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalised),
            static fn (string $token): bool => $token !== '',
        ));
    }

    /**
     * Semua kata dalam satu pola muncul di teks. Urutan tidak penting.
     *
     * @param  list<string>  $patternWords
     */
    public function containsAllWords(string $haystack, array $patternWords): bool
    {
        if ($patternWords === []) {
            return false;
        }

        $tokens = $this->tokens($haystack, aggressive: true);

        foreach ($patternWords as $word) {
            $needle = $this->normaliseAggressive($word);

            if ($needle !== '' && ! in_array($needle, $tokens, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Menghapus aksen, merapatkan huruf berulang, dan mengganti tanda baca
     * dengan spasi.
     *
     * Dua ambang dipakai, dan pemisahan ini penting:
     *
     * Bentuk konservatif memakai run 3+. Run 2 sengaja tidak dirapatkan
     * supaya kata sungguhan tidak berubah: "assalam" dan "ballet" punya
     * huruf kembar. Bentuk konservatif dipakai untuk membandingkan judul
     * dan menghitung kata, jadi kekeliruan di sini merusak fitur.
     *
     * Bentuk agresif memakai run 2+ agar "juuddolll" tertangkap. Aman,
     * karena keluarannya tidak pernah disimpan atau ditampilkan — hanya
     * dipakai menjawab "apakah kata terlarang ini ada di dalam teks".
     *
     * Tanpa pemisahan ini, normalisasi run 3+ saja ternyata tidak defends
     * apa pun: "juuuuddolll" menjadi "juddol", bukan "judol", karena
     * run 4 huruf yang sama hanya menyusut jadi satu huruf.
     */
    private function condense(string $text, int $minRun): string
    {
        $text = strtr($text, self::ACCENTS);

        // Run 3+ jadi satu pada bentuk konservatif; run 2+ ikut satu pada
        // bentuk agresif. "judol" -> "judo" lalu "ol".
        $repeats = $minRun >= 3 ? '2,' : '1,';
        $text = (string) preg_replace('/(.)\1{' . $repeats . '}/u', '$1', $text);

        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
