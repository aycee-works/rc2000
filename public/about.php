<?php
require_once __DIR__ . '/../includes/functions.php';

// Replace this filename with an image placed in public/assets/images/.
$founderImage = 'circlephoto.jpg';
$founderImagePath = __DIR__ . '/assets/images/' . $founderImage;
page_start('About', ['class' => 'about-page']);
?>
<main>
  <section class="page-hero about-hero">
    <div class="narrow">
      <h1>History of Reading Club 2000</h1>
      <p>Reading Club 2000 is an informal library set up by Hernando Guanlao outside his ancestral home in central Makati, Philippines. Hernando Guanlao, known by his nickname Nanie, wanted to encourage his local community to share his joy of reading.</p>
      <a class="button button-gold" href="<?= e(app_url('public/about.php#founder')) ?>">Read More</a>
    </div>
  </section>

  <section class="section about-person" id="founder" data-reveal>
    <?php if (is_file($founderImagePath)): ?>
      <button class="portrait-trigger" type="button" aria-label="Enlarge portrait of Hernando “Mang Nanie” Guanlao" aria-haspopup="dialog" data-portrait-open>
        <img class="founder-portrait" src="<?= e(app_url('public/assets/images/' . $founderImage)) ?>" alt="">
      </button>
      <dialog class="portrait-dialog" aria-labelledby="portrait-caption" data-portrait-dialog>
        <form method="dialog"><button type="submit" autofocus>Close</button></form>
        <figure>
          <img src="<?= e(app_url('public/assets/images/' . $founderImage)) ?>" alt="Hernando “Mang Nanie” Guanlao">
          <figcaption id="portrait-caption">Hernando “Mang Nanie” Guanlao</figcaption>
        </figure>
      </dialog>
    <?php else: ?>
      <div class="portrait-placeholder" aria-hidden="true" data-reveal>NG</div>
    <?php endif; ?>
    <div>
      <h2>Hernando “Mang Nanie” Guanlao</h2>
      <p class="founder-description">Mang Nanie is the founder and caretaker of Reading Club 2000. While studying at PUP, he worked as a library assistant. Over the years, he sold newspapers on sidewalks and sold ice cream. He later worked as a tax accountant, auditor and government consultant. He says he left that work because of what he described as rigged bidding. He also says the streets had more to teach him than any classroom ever could.</p>
      <div id="founder-bio">
        <p class="founder-description">His parents taught him the value of reading and the Golden Rule. He measures success by how much he helps others. Through Reading Club 2000, he gives away used and donated books and promotes education through literature. The library accepts books, school supplies, food and clothing as donations and declines cash donations.</p>
        <p class="founder-description">He has a special respect for street-smart children who never had the chance to finish school. He sees their stories of hunger, work and survival as lessons in their own right. He hopes more teachers, writers and volunteers will share their skills in sports, music or storytelling. His eldest daughter now works alongside him.</p>
        <p class="founder-description">His advice to young people reflects how he lives: “Know your purpose early; don’t chase money, grades; ask your soul, ‘Is this the ride I am supposed to be on?’”</p>
      </div>
      <button class="button button-gold founder-bio-toggle" type="button" aria-controls="founder-bio" aria-expanded="false" data-founder-bio-toggle hidden>See More</button>
    </div>
  </section>

  <section class="section rc-history" id="history" data-reveal>
    <div class="history-heading">
      <span class="eyebrow">A COMMUNITY LIBRARY IN MAKATI</span>
      <h2>The History of Reading Club 2000</h2>
    </div>
    <div class="history-timeline">
      <article class="history-milestone">
        <span class="history-year">2000</span>
        <div>
          <h3>How It Started</h3>
          <p>Hernando “Mang Nanie” Guanlao founded Reading Club 2000 to honor his late parents, who passed on their love of reading. In an interview, he said he left his work as an auditor and government consultant because of what he described as rigged bidding.</p>
          <p>To encourage people to read, he set out about 50 books in front of his home on Balagtas Street in La Paz, Makati. Most came from his own high school and college collection. He arranged them like a newsstand to catch the attention of passersby.</p>
        </div>
      </article>
      <article class="history-milestone">
        <span class="history-year">Early Years</span>
        <div>
          <h3>More Books</h3>
          <p>Neighbors borrowed books and came back with books of their own. Some donors stayed anonymous. Mang Nanie says it feels as though the books want to be read and passed around, and that is how the collection kept growing.</p>
          <p>The library was open around the clock. Readers could take books without a membership, a library card or an ID, and returning books was optional.</p>
        </div>
      </article>
      <article class="history-milestone">
        <span class="history-year">Outreach</span>
        <div>
          <h3>Books on the Move</h3>
          <p>Word spread without advertising. A bicycle library delivered books, newspapers and magazines, and Reading Club 2000 inspired others to start libraries across the country. Mang Nanie also began “book walking,” bringing storybooks to children living on the streets in communities such as San Andres Bukid.</p>
        </div>
      </article>
      <article class="history-milestone">
        <span class="history-year">2012–2018</span>
        <div>
          <h3>In the News</h3>
          <p>By 2012, the library had appeared in international media. In 2018, the Inquirer featured the library on Balagtas Street and traced its beginnings to books from Mang Nanie’s own collection and his family’s shelves.</p>
        </div>
      </article>
      <article class="history-milestone">
        <span class="history-year">2024–2025</span>
        <div>
          <h3>Still Going</h3>
          <p>ABS-CBN featured Mang Nanie and the library in January 2024. A February 2025 feature marked nearly 25 years of his work, and Arab News revisited the library’s beginnings in June 2025.</p>
        </div>
      </article>
      <article class="history-milestone">
        <span class="history-year">Today</span>
        <div>
          <h3>26 Years Later</h3>
          <p>Mang Nanie says around 300 books pass through the library each day, free of charge. The library welcomes books, school supplies, food and clothing as donations and declines cash donations.</p>
          <p>Volunteers offer lessons in music, sports, chess and guitar. Mang Nanie brings books to communities such as Happyland and the Pasig riverside. Children visit the library on their own, some from far away, and his eldest daughter now helps run it.</p>
          <p>The library still promotes education through reading and helps its neighbors. As Mang Nanie puts it, “no one has ever become poor in giving books.”</p>
        </div>
      </article>
    </div>
  </section>

  <section class="section mission-vision" aria-label="Our mission and vision">
    <article class="info-card" data-reveal>
      <h2>Mission</h2>
      <p>The Reading Club is a community-based reading club focused on promoting literacy and free exchange of knowledge. To make books available to anyone who wants them; to save and give access to the true history of those who serve humanity through learning; and to lead young people into the love of learning that is purposeful, original, and upright. In keeping with this principle, we make every effort to present content that is accurate, informative, and respectful of all persons and sources that are included.</p>
    </article>
    <article class="info-card navy" data-reveal>
      <h2>Vision</h2>
      <p>The Reading Club hopes to see a society where books are not just a privilege but a common resource to be freely read and be a source of growth for all Filipinos irrespective of their age and background in knowledge, character and compassion. We hope that our readers of the future will see learning as a means to earn a diploma, but also as a way to add meaning and value to their life and to others.</p>
    </article>
  </section>

  <section class="section watch-section" data-reveal>
    <h2>Watch Us Here!</h2>
    <p>Get to know more about us through our videos.</p>
    <div class="video-wrapper">
      <div class="video-card">
        <iframe
          src="https://www.youtube.com/embed/SPi3Men4B5E"
          title="This 72-year-old grandfather has been giving away free books for two decades | DigiDokyu"
          loading="lazy"
          allowfullscreen>
        </iframe>
      </div>
      <div class="video-card">
        <iframe
          src="https://www.youtube.com/embed/Z0ZyFZ40Cv8?start=3"
          title="He created a Public Library in the Philippines. How Mang Nanie Created Reading Club 2000"
          loading="lazy"
          allowfullscreen>
        </iframe>
      </div>
    </div>
</section>

<section class="section visit-section" data-reveal>
  <div class="section-heading">
    <div><h2>Visit Us</h2><p>Drop by or find us on the map.</p></div>
  </div>
  <div class="visit-grid">
    <div class="visit-info-card">
      <div class="visit-brand">
        <img src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" width="40" height="40" alt="">
        <h3>Reading Club 2000</h3>
      </div>
      <p class="contact-detail">
        <img src="<?= e(app_url('public/assets/images/icons/contactpin_icon.png')) ?>" width="32" height="32" alt="">
        <span>1454 Balagtas St., Barangay La Paz<br>1204 Makati</span>
      </p>
      <p class="contact-detail">
        <img src="<?= e(app_url('public/assets/images/icons/contactcall_icon.png')) ?>" width="32" height="32" alt="">
        <button class="visit-copy" type="button" aria-label="Copy phone number 0977 617 0486" data-copy-phone>0977 617 0486</button>
      </p>
      <a class="contact-detail" href="https://www.facebook.com/ReadingClub2000">
        <img src="<?= e(app_url('public/assets/images/icons/contactfb_icon.png?v=2')) ?>" width="32" height="32" alt="">
        <span>Follow our Facebook Page</span>
      </a>
      <p class="visit-copy-status" role="status" data-copy-status></p>
    </div>
    <div class="visit-map-card">
      <iframe title="Reading Club 2000 map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=1454%20Balagtas%20St%20La%20Paz%20Makati&t=&z=16&ie=UTF8&iwloc=&output=embed"></iframe>
      <a class="button button-map-overlay" href="https://maps.google.com/?q=1454+Balagtas+St+La+Paz+Makati" target="_blank" rel="noopener">Open in Maps</a>
    </div>
  </div>
</section>

</main>
<script src="<?= e(app_url('public/assets/js/about.js?v=2')) ?>" defer></script>
<?php page_end(); ?>
