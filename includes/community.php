<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function featured_campaign(): ?array
{
    try {
        return fetch_one(
            "SELECT c.id, c.title, c.description, c.goal_amount, c.image_path,
                    COALESCE(SUM(d.verified_amount) FILTER (WHERE d.status = 'verified'), 0) AS raised,
                    COUNT(DISTINCT d.donor_email) FILTER (WHERE d.status = 'verified') AS donor_count
             FROM rc2000_admin.campaigns c
             LEFT JOIN rc2000_admin.monetary_donations d ON d.campaign_id = c.id
             WHERE c.status = 'open' AND c.is_featured = TRUE
             GROUP BY c.id LIMIT 1"
        );
    } catch (Throwable $e) {
        error_log('Featured campaign unavailable: ' . $e->getMessage());
        return null;
    }
}

function current_challenge(): ?array
{
    try {
        return fetch_one(
            "SELECT c.id, c.title, c.prompt, c.image_path, c.starts_at, c.ends_at,
                    COUNT(v.id) FILTER (WHERE v.choice = 'accept') AS accept_count,
                    COUNT(v.id) FILTER (WHERE v.choice = 'not_for_me') AS decline_count
             FROM rc2000_admin.challenge_posts c
             LEFT JOIN rc2000_admin.challenge_votes v ON v.challenge_id = c.id
             WHERE c.status = 'published' AND c.starts_at <= now() AND c.ends_at > now()
             GROUP BY c.id ORDER BY c.starts_at DESC LIMIT 1"
        );
    } catch (Throwable $e) {
        error_log('Current challenge unavailable: ' . $e->getMessage());
        return null;
    }
}

function daily_quiz(): ?array
{
    try {
        return fetch_one(
            "SELECT q.id, q.quiz_date
             FROM rc2000_admin.daily_quizzes q
             WHERE q.status = 'published' AND q.quiz_date = CURRENT_DATE
             LIMIT 1"
        );
    } catch (Throwable $e) {
        error_log('Daily quiz unavailable: ' . $e->getMessage());
        return null;
    }
}
