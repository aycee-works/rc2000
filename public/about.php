<?php
require_once __DIR__ . '/../includes/functions.php';

$reviews = published_content('review', 6);
// Replace this filename with an image placed in public/assets/images/.
$founderImage = 'circlephoto.jpg';
$founderImagePath = __DIR__ . '/assets/images/' . $founderImage;
page_start('About');
?>
<main>
  <section class="page-hero red">
    <div class="narrow">
      <h1>History of Reading Club 2000</h1>
      <p>Reading Club 2000 is an informal library set up by Hernando Guanlao outside his ancestral home in central Makati, Philippines. Hernando Guanlao, known by his nickname Nanie, wanted to encourage his local community to share his joy of reading.</p>
      <a class="button button-gold" href="/rc2000/public/about.php#founder">Read More</a>
    </div>
  </section>

  <section class="section about-person" id="founder" data-reveal>
    <?php if (is_file($founderImagePath)): ?>
      <img class="founder-portrait" src="<?= e(app_url('public/assets/images/' . $founderImage)) ?>" alt="Hernando “Mang Nanie” Guanlao" data-reveal>
    <?php else: ?>
      <div class="portrait-placeholder" aria-hidden="true" data-reveal>NG</div>
    <?php endif; ?>
    <div>
      <h2>Hernando “Mang Nanie” Guanlao</h2>
      <p>Mang Nanie is a former tax accountant, ice cream salesman and government employee. He placed about 100 books outside his door to see if anyone would borrow them. Many people did, and they donated other books in return, which grew the collection. (Reuters reports the starting collection as 50 books.)
His main purpose was to honor his parents, who passed on their habit of reading. He says his mission is to give away used and donated books at no cost and to promote education through literature.
The collection has grown through donors, some of whom stay anonymous, and now holds thousands of books. He also does "book walking," giving storybooks to street children, and his eldest daughter is now his partner in the project. Dwell also reports a "book bike" service that delivers reading material to the poorest communities in Manila. As of a February 2025 feature, he has kept the library going for nearly 25 years.
</p>
    </div>
  </section>

  <section class="section" data-reveal>
    <h2>Testimonial</h2>
    <div class="carousel testimonial-carousel" data-carousel>
      <div class="carousel-track">
        <?php if ($reviews): foreach ($reviews as $item): ?>
          <blockquote class="testimonial">
            <p>“<?= e(excerpt($item['body'], 220)) ?>”</p>
            <cite><?= e($item['author_name'] ?: $item['title']) ?></cite>
          </blockquote>
        <?php endforeach; else: ?>
          <blockquote class="testimonial">
            <p>“A library on a sidewalk teaches a whole neighborhood that knowledge isn’t locked behind a fee.”</p>
          </blockquote>
        <?php endif; ?>
      </div>
      <a class="see-all" href="/rc2000/public/explore.php">See all →</a>
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
                title="Video 1"
                allowfullscreen>
            </iframe>
        </div>
        <div class="video-card">
            <iframe
                src="https://www.youtube.com/embed/Z0ZyFZ40Cv8?start=3"
                title="Video 2"
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
      <p class="contact-detail">
        <img src="<?= e(app_url('public/assets/images/icons/contactpin_icon.png')) ?>" width="32" height="32" alt="">
        <span>1454 Balagtas St., Barangay La Paz<br>1204 Makati</span>
      </p>
      <p class="contact-detail">
        <img src="<?= e(app_url('public/assets/images/icons/contactcall_icon.png')) ?>" width="32" height="32" alt="">
        <a href="tel:+639176176486">0977 617 0486</a>
      </p>
      <div class="visit-socials">
        <span>Follow us:</span>
        <a href="#" aria-label="Facebook"><img src="<?= e(app_url('public/assets/images/icons/contactfb_icon.png')) ?>" width="32" height="32" alt=""></a>
      </div>
    </div>
    <div class="visit-map-card">
      <iframe title="Reading Club 2000 map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=1454%20Balagtas%20St%20La%20Paz%20Makati&t=&z=16&ie=UTF8&iwloc=&output=embed"></iframe>
      <a class="button button-map-overlay" href="https://maps.google.com/?q=1454+Balagtas+St+La+Paz+Makati" target="_blank" rel="noopener">Open in Maps</a>
    </div>
  </div>
</section>

</main>
<?php page_end(); ?>
