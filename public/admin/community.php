<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/portal/services/upload-service.php';

try { require_admin(); } catch (RuntimeException $e) { http_response_code(503); exit(e($e->getMessage())); }
$pdo = db();
$errors = [];
$notice = '';

function community_send_donation_email(array $row, string $message): bool
{
    $config = $GLOBALS['APP_CONFIG'] ?? [];
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!MAIL_ENABLED || !is_file($autoload)) return false;
    require_once $autoload;
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP(); $mail->Host=(string)($config['mail_host']??'smtp.gmail.com'); $mail->SMTPAuth=true;
    $mail->Username=(string)($config['mail_username']??''); $mail->Password=(string)($config['mail_password']??'');
    $mail->Port=(int)($config['mail_port']??587);
    $mail->SMTPSecure=($config['mail_encryption']??'tls')==='ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet='UTF-8'; $mail->setFrom((string)($config['mail_from_email']??$mail->Username),(string)($config['mail_from_name']??'Reading Club 2000'));
    $mail->addAddress((string)$row['donor_email'],(string)$row['donor_name']); $mail->isHTML(false);
    $mail->Subject='Donation report update · '.(string)$row['reference_no'];
    $campaignLine=!empty($row['campaign_title'])?"Campaign: {$row['campaign_title']}\n":'';
    $mail->Body="Hello {$row['donor_name']},\n\nYour donation report {$row['reference_no']} is now marked: {$row['status_label']}.\n{$campaignLine}Reported amount: ₱".number_format((float)$row['reported_amount'],2)."\n\n{$message}\n\nThank you,\nReading Club 2000";
    $mail->send(); return true;
}

if (is_post()) {
    csrf_require();
    $action = post_str('action');
    try {
        if ($action === 'save_campaign') {
            $id=(int)post_str('id'); $title=post_str('title'); $description=post_str('description'); $goal=post_str('goal_amount');
            $status=in_array(post_str('status'),['open','closed'],true)?post_str('status'):'closed';
            if ($title==='' || mb_strlen($title)>160) $errors[]='Campaign title is required (160 characters maximum).';
            if ($description==='' || mb_strlen($description)>5000) $errors[]='Add a campaign description and spending plan (5,000 characters maximum).';
            if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$goal) || (float)$goal<=0) $errors[]='Enter a campaign goal greater than zero.';
            $imagePath=null;
            if (upload_provided($_FILES['image']??null)) { $upload=save_uploaded_image($_FILES['image'],'campaigns'); if (!$upload['ok']) $errors[]=$upload['message']; else $imagePath=$upload['path']; }
            if (!$errors) {
                $featured=isset($_POST['is_featured']);
                $featuredDb=$featured?'true':'false';
                $pdo->beginTransaction();
                if ($featured) $pdo->exec('UPDATE rc2000_admin.campaigns SET is_featured=FALSE WHERE is_featured=TRUE');
                if ($id>0) {
                    $sql='UPDATE rc2000_admin.campaigns SET title=?,description=?,goal_amount=?,status=?,is_featured=?'.($imagePath?',image_path=?':'').' WHERE id=?';
                    $params=[$title,$description,$goal,$status,$featuredDb]; if($imagePath)$params[]=$imagePath; $params[]=$id; $pdo->prepare($sql)->execute($params);
                } else {
                    $pdo->prepare('INSERT INTO rc2000_admin.campaigns(title,description,goal_amount,status,is_featured,image_path,created_by) VALUES(?,?,?,?,?,?,?)')->execute([$title,$description,$goal,$status,$featuredDb,$imagePath,current_admin()['id']??null]);
                }
                $pdo->commit(); flash('success','Campaign saved.'); redirect('admin/community.php#campaigns');
            }
        } elseif ($action==='toggle_campaign') {
            $pdo->prepare("UPDATE rc2000_admin.campaigns SET status=CASE WHEN status='open' THEN 'closed' ELSE 'open' END WHERE id=?")->execute([(int)post_str('id')]); flash('success','Campaign status updated.'); redirect('admin/community.php#campaigns');
        } elseif ($action==='save_challenge') {
            $id=(int)post_str('id'); $title=post_str('title'); $prompt=post_str('prompt'); $start=post_str('starts_at'); $end=post_str('ends_at');
            if($title===''||mb_strlen($title)>160)$errors[]='Challenge title is required (160 characters maximum).';
            if($prompt===''||mb_strlen($prompt)>3000)$errors[]='Add the challenge prompt (3,000 characters maximum).';
            if(!$start||!$end||strtotime($end)<=strtotime($start))$errors[]='Choose a valid start and end time.';
            $imagePath=null; if(upload_provided($_FILES['image']??null)){ $upload=save_uploaded_image($_FILES['image'],'challenges'); if(!$upload['ok'])$errors[]=$upload['message'];else$imagePath=$upload['path']; }
            $challengeStatus=in_array(post_str('status'),['draft','published','closed'],true)?post_str('status'):'published';
            if(!$errors){
                if($id>0){$sql='UPDATE rc2000_admin.challenge_posts SET title=?,prompt=?,starts_at=?,ends_at=?,status=?,'.($imagePath?'image_path=?,':'').' updated_at=now() WHERE id=?';$params=[$title,$prompt,date('c',strtotime($start)),date('c',strtotime($end)),$challengeStatus];if($imagePath)$params[]=$imagePath;$params[]=$id;$pdo->prepare($sql)->execute($params);}
                else $pdo->prepare('INSERT INTO rc2000_admin.challenge_posts(title,prompt,image_path,starts_at,ends_at,status,created_by) VALUES(?,?,?,?,?,?,?)')->execute([$title,$prompt,$imagePath,date('c',strtotime($start)),date('c',strtotime($end)),$challengeStatus,current_admin()['id']??null]);
                flash('success','Weekly challenge saved.');redirect('admin/content/index.php#weekly-challenge');
            }
        } elseif ($action==='archive_challenge') {
            $challengeId=(int)post_str('id');
            if($challengeId<1)$errors[]='Select a weekly challenge to archive.';
            else{
                $stmt=$pdo->prepare("UPDATE rc2000_admin.challenge_posts SET status='closed' WHERE id=? RETURNING id");
                $stmt->execute([$challengeId]);
                if(!$stmt->fetchColumn())$errors[]='That weekly challenge could not be found.';
                else{flash('success','Weekly challenge archived.');redirect('admin/content/index.php#weekly-challenge');}
            }
        } elseif ($action==='save_quiz') {
            $date=post_str('quiz_date')?:date('Y-m-d'); $status=post_str('quiz_status')==='published'?'published':'draft';
            $submittedQuestions=$_POST['questions']??null;
            if(!is_array($submittedQuestions)||count($submittedQuestions)!==5)$errors[]='Create exactly five questions.';
            $parsed=[];
            foreach(is_array($submittedQuestions)?array_values($submittedQuestions):[] as $n=>$item){
                if(!is_array($item)){$errors[]='Check question '.($n+1).' and its answer options.';break;}
                $question=trim(is_string($item['question']??null)?$item['question']:'');
                $rawOptions=$item['options']??null;
                if($question===''||mb_strlen($question)>1000||!is_array($rawOptions)||count($rawOptions)<2||count($rawOptions)>5){$errors[]='Question '.($n+1).' needs text and 2–5 answer options.';break;}
                $options=[];
                foreach(array_values($rawOptions) as $option){$option=trim(is_string($option)?$option:'');if($option===''||mb_strlen($option)>500){$errors[]='Every answer option must have text (500 characters maximum).';break 2;}$options[]=$option;}
                $correct=filter_var($item['correct']??null,FILTER_VALIDATE_INT);
                if($correct===false||$correct<0||$correct>=count($options)){$errors[]='Choose the correct answer for question '.($n+1).'.';break;}
                $parsed[]=[$question,$options,$correct];
            }
            $quizId = (int) post_str('id');
            $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dateValue || $dateValue->format('Y-m-d') !== $date) {
                $errors[] = 'Choose a valid quiz date.';
            }
            if (!$errors) {
                try {
                    $pdo->beginTransaction();
                    if ($quizId > 0) {
                        $existing = $pdo->prepare('SELECT id FROM rc2000_admin.daily_quizzes WHERE id=? FOR UPDATE');
                        $existing->execute([$quizId]);
                        if (!$existing->fetchColumn()) {
                            throw new RuntimeException('That quiz could not be found. Create a new quiz instead.');
                        }
                        $duplicate = $pdo->prepare('SELECT 1 FROM rc2000_admin.daily_quizzes WHERE quiz_date=? AND id<>?');
                        $duplicate->execute([$date, $quizId]);
                        if ($duplicate->fetchColumn()) {
                            throw new RuntimeException('A quiz is already scheduled for that date. Choose another date.');
                        }
                        $pdo->prepare('UPDATE rc2000_admin.daily_quizzes SET quiz_date=?, status=?, updated_at=now() WHERE id=?')->execute([$date, $status, $quizId]);
                    } else {
                        $create = $pdo->prepare('INSERT INTO rc2000_admin.daily_quizzes(quiz_date,status,created_by) VALUES(?,?,?) RETURNING id');
                        $create->execute([$date, $status, current_admin()['id'] ?? null]);
                        $quizId = (int) $create->fetchColumn();
                    }
                    $pdo->prepare('DELETE FROM rc2000_admin.daily_quiz_questions WHERE quiz_id=?')->execute([$quizId]);
                    $insert = $pdo->prepare('INSERT INTO rc2000_admin.daily_quiz_questions(quiz_id,question_order,question,options,correct_option) VALUES(?,?,?,?,?)');
                    foreach ($parsed as $i => $q) {
                        $insert->execute([$quizId, $i + 1, $q[0], json_encode($q[1], JSON_UNESCAPED_UNICODE), $q[2]]);
                    }
                    $pdo->commit();
                    flash('success', 'Five-question quiz saved.');
                    redirect('admin/content/index.php#daily-quiz');
                } catch (Throwable $saveError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Quiz save failed: ' . $saveError->getMessage());
                    $errors[] = $saveError instanceof RuntimeException
                        ? $saveError->getMessage()
                        : ((string) $saveError->getCode() === '23505'
                            ? 'A quiz is already scheduled for that date. Choose another date.'
                            : 'The quiz could not be saved. Please try again.');
                }
            }
        } elseif ($action==='publish_leaderboard') {
            $quizId=(int)post_str('quiz_id');$dateStmt=$pdo->prepare('SELECT quiz_date FROM rc2000_admin.daily_quizzes WHERE id=?');$dateStmt->execute([$quizId]);$quizDate=(string)$dateStmt->fetchColumn();$weekStart=$quizDate?date('Y-m-d',strtotime('monday this week',strtotime($quizDate))):'';$weekEnd=$weekStart?date('Y-m-d',strtotime($weekStart.' +7 days')):'';
            $exists=$pdo->prepare('SELECT 1 FROM rc2000_admin.quiz_leaderboard_posts WHERE week_start=?');$exists->execute([$weekStart]);if($exists->fetchColumn())$errors[]='A leaderboard post already exists for this week.';
            $stmt=$pdo->prepare('SELECT s.username,SUM(s.score) AS score,COUNT(*) AS quiz_count FROM rc2000_admin.quiz_submissions s JOIN rc2000_admin.daily_quizzes q ON q.id=s.quiz_id WHERE q.quiz_date>=? AND q.quiz_date<? GROUP BY s.username ORDER BY SUM(s.score) DESC,COUNT(*) DESC,LOWER(s.username) ASC LIMIT 20');$stmt->execute([$weekStart,$weekEnd]);$rows=$stmt->fetchAll();if(!$rows)$errors[]='There are no scores to publish for this week yet.';
            if(!$errors){$pdo->beginTransaction();$body="Weekly community leaderboard for the week beginning ".$weekStart.". Scores combine daily five-question quizzes.\n\n";foreach($rows as $i=>$row)$body.=sprintf("%d. %s — %d points across %d quiz%s\n",$i+1,$row['username'],$row['score'],$row['quiz_count'],(int)$row['quiz_count']===1?'':'zes');$title='Weekly Reading Quiz Leaderboard · '.date('M j, Y',strtotime($weekStart));$adminId=(int)(current_admin()['id']??0);$stmt=$pdo->prepare("INSERT INTO rc2000_admin.stories(type,title,body,author,status,published_at,created_by) VALUES('article',?,?,?,'published',now(),?) RETURNING id");$stmt->execute([$title,$body,'Reading Club 2000',$adminId?:null]);$storyId=(int)$stmt->fetchColumn();$pdo->prepare('INSERT INTO rc2000_admin.quiz_leaderboard_posts(quiz_id,story_id,week_start) VALUES(?,?,?)')->execute([$quizId,$storyId,$weekStart]);$pdo->commit();flash('success','Weekly leaderboard article published in Manage content.');redirect('admin/content/index.php');}
        } elseif ($action==='unpublish_leaderboard') {
            $quizId=(int)post_str('quiz_id');
            if($quizId<1)$errors[]='Select a weekly leaderboard to unpublish.';
            else{
                $pdo->beginTransaction();
                $postStmt=$pdo->prepare('SELECT story_id FROM rc2000_admin.quiz_leaderboard_posts WHERE quiz_id=? FOR UPDATE');
                $postStmt->execute([$quizId]);
                $leaderboardPost=$postStmt->fetch();
                if(!$leaderboardPost){$pdo->rollBack();$errors[]='That weekly leaderboard is no longer published.';}
                else{
                    $pdo->prepare("UPDATE rc2000_admin.stories SET status='draft',published_at=NULL WHERE id=?")->execute([(int)$leaderboardPost['story_id']]);
                    $pdo->prepare('DELETE FROM rc2000_admin.quiz_leaderboard_posts WHERE quiz_id=?')->execute([$quizId]);
                    $pdo->commit();
                    flash('success','Weekly leaderboard article moved to Draft. Quiz submissions were kept.');
                    redirect('admin/content/index.php#daily-quiz');
                }
            }
        } elseif ($action==='review_campaign_donation') {
            $id=(int)post_str('id');$decision=post_str('decision');$amount=post_str('verified_amount');$message=post_str('admin_message');
            if(!in_array($decision,['verified','not_verified'],true))$errors[]='Choose a valid decision.';
            if($decision==='verified'&&(!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$amount)||(float)$amount<=0))$errors[]='Enter the amount confirmed by the admin.';
            if(mb_strlen($message)>2000)$errors[]='Message must be 2,000 characters or fewer.';
            if(!$errors){$stmt=$pdo->prepare('SELECT d.*,c.title AS campaign_title FROM rc2000_admin.monetary_donations d LEFT JOIN rc2000_admin.campaigns c ON c.id=d.campaign_id WHERE d.id=?');$stmt->execute([$id]);$row=$stmt->fetch();if(!$row)$errors[]='Donation report not found.';else{$pdo->prepare('UPDATE rc2000_admin.monetary_donations SET status=?,verified_amount=?,verification_note=?,admin_message=?,verified_by=?,verified_at=now() WHERE id=?')->execute([$decision,$decision==='verified'?$amount:null,post_str('verification_note')?:null,$message?:null,(int)(current_admin()['id']??0)?:null,$id]);$row['status_label']=$decision==='verified'?'Donation verified':'Donation not verified';$emailStatus='not_configured';$emailedAt=null;if($message!==''){try{if(community_send_donation_email($row,$message)){$emailStatus='sent';$emailedAt=date('c');}else$emailStatus='not_configured';}catch(Throwable $e){error_log('Donation update email failed: '.$e->getMessage());$emailStatus='failed';}}$pdo->prepare('UPDATE rc2000_admin.monetary_donations SET email_status=?,emailed_at=? WHERE id=?')->execute([$emailStatus,$emailedAt,$id]);flash('success',$emailStatus==='sent'?'Donation reviewed and email sent.':'Donation review saved. Email status: '.$emailStatus.'.');redirect('admin/community.php#reports');}}
        }
    } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();error_log('Community admin action failed: '.$e->getMessage());$errors[]='The change could not be saved. Check that database/community_features.sql has been applied.'; }
    if ($errors && in_array($action, ['save_challenge', 'archive_challenge', 'save_quiz', 'publish_leaderboard', 'unpublish_leaderboard'], true)) {
        flash('error', implode(' ', $errors));
        if (in_array($action, ['save_challenge', 'archive_challenge'], true)) {
            redirect('admin/content/index.php#weekly-challenge');
        }
        if ($action === 'save_quiz') {
            $quizReturn = $quizId > 0 ? '?quiz=' . $quizId : '?new_quiz=1';
            redirect('admin/content/index.php' . $quizReturn . '#daily-quiz');
        }
        redirect('admin/content/index.php#daily-quiz');
    }
}

$editId=(int)($_GET['campaign']??0);$editCampaign=null;if($editId){$s=$pdo->prepare('SELECT * FROM rc2000_admin.campaigns WHERE id=?');$s->execute([$editId]);$editCampaign=$s->fetch()?:null;}
$campaigns=$pdo->query("SELECT c.*,COALESCE(SUM(d.verified_amount) FILTER(WHERE d.status='verified'),0) AS raised FROM rc2000_admin.campaigns c LEFT JOIN rc2000_admin.monetary_donations d ON d.campaign_id=c.id GROUP BY c.id ORDER BY c.is_featured DESC,c.created_at DESC")->fetchAll();
$reports=$pdo->query("SELECT d.id,d.reference_no,d.donor_name,d.donor_email,d.reported_amount,d.status,d.email_status,c.title AS campaign_title FROM rc2000_admin.monetary_donations d LEFT JOIN rc2000_admin.campaigns c ON c.id=d.campaign_id ORDER BY (d.status='pending') DESC,d.created_at DESC LIMIT 30")->fetchAll();
$pageTitle='Campaigns & community';$activeNav='community';$extraCss=['community.css'];require dirname(__DIR__,2).'/includes/portal/admin-header.php';
?>
<header class="page-header"><h1 class="page-title">Manage Campaigns</h1><p class="page-subtitle">Manage campaign transparency and donation verification.</p></header>
<?php if($errors): ?><div class="flash flash-error" role="alert"><?php foreach($errors as $error): ?><p><?=e($error)?></p><?php endforeach; ?></div><?php endif; ?>
<div class="community-admin-grid">
<section class="panel" id="campaigns"><div class="panel-header"><h2 class="panel-title"><?= $editCampaign?'Edit campaign':'Create campaign' ?></h2></div><form class="panel-body community-form" method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="save_campaign"><input type="hidden" name="id" value="<?= (int)($editCampaign['id']??0) ?>"><label>Campaign title<input name="title" maxlength="160" required value="<?=e($editCampaign['title']??'')?>"></label><label>Campaign goal (PHP)<input name="goal_amount" type="number" min="1" step="0.01" required value="<?=e($editCampaign['goal_amount']??'')?>"></label><label>Description and spending plan<textarea name="description" rows="4" maxlength="5000" required><?=e($editCampaign['description']??'')?></textarea></label><label>Campaign image (JPG, PNG, WebP)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><label>Status<select name="status"><option value="open" <?=($editCampaign['status']??'open')==='open'?'selected':''?>>Open</option><option value="closed" <?=($editCampaign['status']??'')==='closed'?'selected':''?>>Closed</option></select></label><label class="check"><input type="checkbox" name="is_featured" value="1" <?=!empty($editCampaign['is_featured'])?'checked':''?>> Show campaign summary on homepage (only one can be featured)</label><button class="btn btn-maroon" type="submit">Save campaign</button></form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Campaign</th><th>Progress</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($campaigns as $c): ?><tr><td><strong><?=e($c['title'])?></strong><br><small>Goal <?=e(format_peso($c['goal_amount']))?></small></td><td><?=e(format_peso($c['raised']))?></td><td><?=e(ucfirst($c['status']))?><?=!empty($c['is_featured'])?' · Homepage':''?></td><td><a href="<?=e(url('admin/community.php?campaign='.(int)$c['id'].'#campaigns'))?>">Edit</a><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="toggle_campaign"><input type="hidden" name="id" value="<?=(int)$c['id']?>"><button class="link-button" type="submit"><?=$c['status']==='open'?'Close':'Reopen'?></button></form></td></tr><?php endforeach; if(!$campaigns): ?><tr><td colspan="4">No campaigns yet.</td></tr><?php endif; ?></tbody></table></div></section>

<section class="panel" id="reports"><div class="panel-header"><h2 class="panel-title">Donation Reports</h2></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Tracking ID / donor</th><th>Campaign / amount</th><th>Status</th><th>Review & email</th></tr></thead><tbody><?php foreach($reports as $d): ?><tr><td><strong><?=e($d['reference_no'])?></strong><br><?=e($d['donor_name'])?><br><small><?=e($d['donor_email'])?></small></td><td><?=e($d['campaign_title']?:'Monetary donation')?><br><?=e(format_peso($d['reported_amount']))?></td><td><?=e(str_replace('_',' ',$d['status']))?><br><small>Email: <?=e($d['email_status'])?></small></td><td><details><summary>Review</summary><form method="post" class="community-form compact-form"><?=csrf_field()?><input type="hidden" name="action" value="review_campaign_donation"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><label>Decision<select name="decision"><option value="verified">Verify donation</option><option value="not_verified">Not verified</option></select></label><label>Verified amount<input name="verified_amount" type="number" min="0.01" step="0.01" value="<?=e($d['reported_amount'])?>"></label><label>Verification note<input name="verification_note" maxlength="255"></label><label>Email message<textarea name="admin_message" rows="3" maxlength="2000" required></textarea></label><button class="btn btn-maroon" type="submit">Save & email donor</button><small>Message is saved even when email delivery is not configured.</small></form></details></td></tr><?php endforeach; if(!$reports): ?><tr><td colspan="4">No campaign donation reports yet.</td></tr><?php endif; ?></tbody></table></div></section>


</div>
<?php require dirname(__DIR__,2).'/includes/portal/admin-footer.php'; ?>
