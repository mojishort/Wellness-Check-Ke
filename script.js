const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

// Toggle menu
menuToggle.addEventListener('click', () => {
  navLinks.classList.toggle('active');
});

// Close menu when a link is clicked (mobile only)
document.querySelectorAll('#navLinks a').forEach(link => {
  link.addEventListener('click', () => {
    navLinks.classList.remove('active');
  });
});



// --- Code for Highlighting Active Link ---

document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('#navLinks a');
    
    // 1. Get the current page's file name from the URL
    // e.g., if URL is http://example.com/about.html, this extracts 'about.html'
    const currentPage = window.location.pathname.split('/').pop();
    
    // If the URL is just the root (e.g., http://example.com/), assume it's the home page
    const filename = currentPage === '' ? 'homepage.html' : currentPage;
    
    // 2. Loop through all navigation links
    navLinks.forEach(link => {
        // Get the link's href attribute (e.g., 'about.html')
        const linkHref = link.getAttribute('href');
        
        // 3. Compare the link's href with the current page's filename
        if (linkHref === filename) {
            // Found a match! Add the active class
            link.classList.add('active-link');
            
            // Special handling for sub-navigation: Highlight the main 'About' parent link
            // if we are on a sub-page like #mission or #vision (assuming they are on about.html)
            const parentLi = link.closest('.dropdown');
            if (parentLi) {
                // Find the main 'About' link (the first <a> under the parent li)
                const parentLink = parentLi.querySelector(':scope > a');
                if (parentLink) {
                    parentLink.classList.add('active-link');
                }
            }
        }
    });
});




  const carousel = document.querySelector('.services-carousel');
  const leftBtn = document.querySelector('.left-btn');
  const rightBtn = document.querySelector('.right-btn');

  const cardWidth = 320; // card width + gap

  rightBtn.addEventListener('click', () => {
    carousel.scrollBy({ left: cardWidth, behavior: 'smooth' });
  });

  leftBtn.addEventListener('click', () => {
    carousel.scrollBy({ left: -cardWidth, behavior: 'smooth' });
  });

  // Auto-slide every 4s
  setInterval(() => {
    if (carousel.scrollLeft + carousel.offsetWidth >= carousel.scrollWidth) {
      // go back to start when reaching the end
      carousel.scrollTo({ left: 0, behavior: 'smooth' });
    } else {
      carousel.scrollBy({ left: cardWidth, behavior: 'smooth' });
    }
  }, 4000);



document.querySelectorAll('.service-item.with-overlay').forEach(card => {
  card.addEventListener('click', () => {
    card.classList.toggle('active');
  });
});



  



