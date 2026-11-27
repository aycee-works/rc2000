-- Run after 01_create_tables.sql. Existing set_updated_at function and triggers come from the export.
-- TOC entry 275 (class 1255 OID 16407)
-- Name: set_updated_at(); Type: FUNCTION; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE FUNCTION rc2000_admin.set_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$;



SET default_tablespace = '';

SET default_table_access_method = heap;

--

-- TOC entry 3517 (class 2620 OID 16701)
-- Name: admins trg_admins_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_admins_updated BEFORE UPDATE ON rc2000_admin.admins FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3522 (class 2620 OID 16707)
-- Name: announcements trg_announcements_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_announcements_updated BEFORE UPDATE ON rc2000_admin.announcements FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3524 (class 2620 OID 16935)
-- Name: campaigns trg_campaigns_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_campaigns_updated BEFORE UPDATE ON rc2000_admin.campaigns FOR EACH ROW EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3525 (class 2620 OID 16936)
-- Name: challenge_posts trg_challenges_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_challenges_updated BEFORE UPDATE ON rc2000_admin.challenge_posts FOR EACH ROW EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3526 (class 2620 OID 16937)
-- Name: daily_quizzes trg_daily_quizzes_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_daily_quizzes_updated BEFORE UPDATE ON rc2000_admin.daily_quizzes FOR EACH ROW EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3520 (class 2620 OID 16705)
-- Name: gallery_images trg_gallery_images_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_gallery_images_updated BEFORE UPDATE ON rc2000_admin.gallery_images FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3519 (class 2620 OID 16704)
-- Name: inquiries trg_inquiries_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_inquiries_updated BEFORE UPDATE ON rc2000_admin.inquiries FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3518 (class 2620 OID 16703)
-- Name: monetary_donations trg_monetary_donations_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_monetary_donations_updated BEFORE UPDATE ON rc2000_admin.monetary_donations FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3521 (class 2620 OID 16706)
-- Name: stories trg_stories_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_stories_updated BEFORE UPDATE ON rc2000_admin.stories FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--

-- TOC entry 3523 (class 2620 OID 16708)
-- Name: trivia trg_trivia_updated; Type: TRIGGER; Schema: rc2000_admin; Owner: rc2000_db_user
--

CREATE TRIGGER trg_trivia_updated BEFORE UPDATE ON rc2000_admin.trivia FOR EACH ROW WHEN ((old.* IS DISTINCT FROM new.*)) EXECUTE FUNCTION rc2000_admin.set_updated_at();


--
-- Two teaching views. They are not currently used by the PHP application.
CREATE OR REPLACE VIEW rc2000_admin.v_campaign_progress AS
SELECT c.id AS campaign_id, c.title, c.goal_amount, c.status,
       COALESCE(SUM(d.verified_amount) FILTER (WHERE d.status = 'verified'), 0) AS verified_raised,
       COUNT(d.id) FILTER (WHERE d.status = 'pending') AS pending_reports
FROM rc2000_admin.campaigns c
LEFT JOIN rc2000_admin.monetary_donations d ON d.campaign_id = c.id
GROUP BY c.id, c.title, c.goal_amount, c.status;

CREATE OR REPLACE VIEW rc2000_admin.v_inquiry_workload AS
SELECT i.category, i.status, COUNT(*) AS inquiry_count,
       MAX(i.created_at) AS newest_inquiry
FROM rc2000_admin.inquiries i
GROUP BY i.category, i.status;
