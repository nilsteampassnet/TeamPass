<?php

declare(strict_types=1);

namespace ZxcvbnPhp;

/**
 * Feedback - gives some user guidance based on the strength
 * of a password
 *
 * @see zxcvbn/src/time_estimates.coffee
 */
class TimeEstimator
{
    /**
     * @param int|float $guesses
     * @return array
     */
    public function estimateAttackTimes(float $guesses): array
    {
        $crack_times_seconds = [
            'online_throttling_100_per_hour' => $guesses / (100 / 3600),
            'online_no_throttling_10_per_second' => $guesses / 10,
            'offline_slow_hashing_1e4_per_second' => $guesses / 1e4,
            'offline_fast_hashing_1e10_per_second' => $guesses / 1e10
        ];

        $crack_times_display = array_map(
            [ $this, 'displayTime' ],
            $crack_times_seconds
        );

        return [
            'crack_times_seconds' => $crack_times_seconds,
            'crack_times_display' => $crack_times_display,
            'score'               => $this->guessesToScore($guesses)
        ];
    }

    protected function guessesToScore(float $guesses): int
    {
        $DELTA = 5;

        if ($guesses < 1e3 + $DELTA) {
            # risky password: "too guessable"
            return 0;
        }

        if ($guesses < 1e6 + $DELTA) {
            # modest protection from throttled online attacks: "very guessable"
            return 1;
        }

        if ($guesses < 1e8 + $DELTA) {
            # modest protection from unthrottled online attacks: "somewhat guessable"
            return 2;
        }

        if ($guesses < 1e10 + $DELTA) {
            # modest protection from offline attacks: "safely unguessable"
            # assuming a salted, slow hash function like bcrypt, scrypt, PBKDF2, argon, etc
            return 3;
        }

        # strong protection from offline attacks under same scenario: "very unguessable"
        return 4;
    }

    protected function displayTime(float $seconds): string
    {
        $callback = function (float $seconds): array {
            $minute = 60;
            $hour = $minute * 60;
            $day = $hour * 24;
            $month = $day * 31;
            $year = $month * 12;
            $century = $year * 100;

            if ($seconds < 1) {
                return [null, 'less than a second'];
            }

            if ($seconds < $minute) {
                $base = round($seconds);
                return [$base, "$base second"];
            }

            if ($seconds < $hour) {
                $base = round($seconds / $minute);
                return [$base, "$base minute"];
            }

            if ($seconds < $day) {
                $base = round($seconds / $hour);
                return [$base, "$base hour"];
            }

            if ($seconds < $month) {
                $base = round($seconds / $day);
                return [$base, "$base day"];
            }

            if ($seconds < $year) {
                $base = round($seconds / $month);
                return [$base, "$base month"];
            }

            if ($seconds < $century) {
                $base = round($seconds / $year);
                return [$base, "$base year"];
            }

            return [null, 'centuries'];
        };

        [$display_num, $display_str] = $callback($seconds);

        if ($display_num > 1) {
            $display_str .= 's';
        }

        return $display_str;
    }
}
