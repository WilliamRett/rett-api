<?php

namespace Database\Factories;

use App\Models\Collaborator;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollaboratorFactory extends Factory
{
    protected $model = Collaborator::class;

    /**
     * Generate a valid CPF (digits only). You can format it later if needed.
     */
    private function generateCpf(bool $formatted = false): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) $n[$i] = random_int(0, 9);

        $sum = 0; for ($i = 0, $w = 10; $i < 9; $i++, $w--) $sum += $n[$i] * $w;
        $d1 = 11 - ($sum % 11); $d1 = ($d1 >= 10) ? 0 : $d1;

        $sum = 0; for ($i = 0, $w = 11; $i < 9; $i++, $w--) $sum += $n[$i] * $w;
        $sum += $d1 * 2;
        $d2 = 11 - ($sum % 11); $d2 = ($d2 >= 10) ? 0 : $d2;

        $digits = sprintf('%d%d%d%d%d%d%d%d%d%d%d',
            $n[0],$n[1],$n[2],$n[3],$n[4],$n[5],$n[6],$n[7],$n[8],$d1,$d2
        );

        if ($formatted) {
            return substr($digits,0,3).'.'.substr($digits,3,3).'.'.substr($digits,6,3).'-'.substr($digits,9,2);
        }
        return $digits;
    }

    /**
     * Very simple phone digits generator (11 digits).
     */
    private function phoneDigits(int $len = 11): string
    {
        $ddd  = str_pad((string)random_int(11, 99), 2, '0', STR_PAD_LEFT);
        $rest = str_pad((string)random_int(900000000, 999999999), 9, '0', STR_PAD_LEFT);
        return substr($ddd.$rest, 0, $len);
    }

    /**
     * States as full names (title case).
     */
    private const STATE_NAMES = [
        'Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará', 'Distrito Federal', 'Espírito Santo',
        'Goiás', 'Maranhão', 'Mato Grosso', 'Mato Grosso do Sul', 'Minas Gerais', 'Pará', 'Paraíba',
        'Paraná', 'Pernambuco', 'Piauí', 'Rio de Janeiro', 'Rio Grande do Norte', 'Rio Grande do Sul',
        'Rondônia', 'Roraima', 'Santa Catarina', 'São Paulo', 'Sergipe', 'Tocantins',
    ];

    /**
     * Default collaborator factory definition.
     * NOTE: "state" uses full state names, not UF.
     */
    public function definition(): array
    {
        return [
            'name'   => $this->faker->name(),
            'email'  => $this->faker->unique()->safeEmail(),
            'cpf'    => $this->generateCpf(false),
            'city'   => $this->faker->city(),
            'state'  => $this->faker->randomElement(self::STATE_NAMES),
            'phone'  => $this->phoneDigits(11),
        ];
    }
}
