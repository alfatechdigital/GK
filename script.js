'use strict';

const gsap = window.gsap;
const ScrollTrigger = window.ScrollTrigger;
const scrollElement = document.querySelector('.scrollElement');
const leafArt = document.querySelector('.leaf-art');
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('.members-dialog').forEach((dialog) => {
  const gallery = dialog.querySelector('.members-gallery');
  const cards = Array.from(gallery.querySelectorAll('.member-profile'));
  let activeIndex = 0;

  const scrollToCard = (index) => {
    activeIndex = (index + cards.length) % cards.length;
    const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    gallery.scrollTo({
      left: cards[activeIndex].offsetLeft - cards[0].offsetLeft,
      behavior
    });
  };

  dialog.querySelector('[data-members-prev]').addEventListener('click', () => scrollToCard(activeIndex - 1));
  dialog.querySelector('[data-members-next]').addEventListener('click', () => scrollToCard(activeIndex + 1));
  gallery.addEventListener('scroll', () => {
    const galleryCenter = gallery.getBoundingClientRect().left + gallery.clientWidth / 2;
    activeIndex = cards.reduce((nearestIndex, card, index) => {
      const cardBounds = card.getBoundingClientRect();
      const nearestBounds = cards[nearestIndex].getBoundingClientRect();
      const cardCenter = cardBounds.left + cardBounds.width / 2;
      const nearestCenter = nearestBounds.left + nearestBounds.width / 2;
      return Math.abs(cardCenter - galleryCenter) < Math.abs(nearestCenter - galleryCenter) ? index : nearestIndex;
    }, 0);
  }, { passive: true });
});

document.querySelectorAll('[data-members-open]').forEach((button) => {
  button.addEventListener('click', () => {
    document.getElementById(button.dataset.membersOpen)?.showModal();
  });
});

document.querySelectorAll('[data-members-close]').forEach((button) => {
  button.addEventListener('click', () => button.closest('.members-dialog').close());
});

document.querySelectorAll('.members-dialog').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
});

if (!gsap || !ScrollTrigger || !scrollElement || !leafArt) {
  console.error('Gelar Karya: GSAP, ScrollTrigger, atau elemen adegan tidak berhasil dimuat.');
} else {
  gsap.registerPlugin(ScrollTrigger);

  const sceneSvg = document.querySelector('.parallax');
  const sceneHeight = sceneSvg ? sceneSvg.getBBox().height : 500;
  const siteHeader = document.querySelector('.site-header');
  const projectPhotoTimelines = [];
  const projectPhotoTriggers = [];

  const setupProjectPhotoSequences = () => {
    projectPhotoTimelines.forEach((timeline) => {
      timeline.scrollTrigger?.kill();
      timeline.kill();
    });
    projectPhotoTriggers.forEach((trigger) => trigger.kill());
    projectPhotoTimelines.length = 0;
    projectPhotoTriggers.length = 0;

    document.querySelectorAll('[data-photo-sequence]').forEach((sequence) => {
      const photos = Array.from(sequence.querySelectorAll('.project-photo'));
      const stage = sequence.querySelector('.project-photo-stage');
      if (photos.length === 0 || !stage) return;

      const headerOffset = (siteHeader?.getBoundingClientRect().height || 0) + 16;
      stage.style.top = `${headerOffset}px`;
      sequence.style.height = `${stage.offsetHeight + Math.max(0, photos.length - 1) * window.innerHeight}px`;
      gsap.set(photos, { autoAlpha: 0 });
      gsap.set(photos[0], { autoAlpha: 1 });

      if (photos.length < 2) return;

      const updateActivePhoto = (progress) => {
        const activeIndex = Math.min(photos.length - 1, Math.round(progress * (photos.length - 1)));
        photos.forEach((photo, index) => {
          photo.setAttribute('aria-hidden', String(index !== activeIndex));
        });
        const counter = sequence.querySelector('[data-photo-current]');
        if (counter) counter.textContent = String(activeIndex + 1).padStart(2, '0');
      };

      if (prefersReducedMotion) {
        projectPhotoTriggers.push(ScrollTrigger.create({
          trigger: sequence,
          start: () => `top top+=${headerOffset}`,
          end: () => `+=${(photos.length - 1) * window.innerHeight}`,
          onUpdate: (trigger) => {
            const activeIndex = Math.min(photos.length - 1, Math.round(trigger.progress * (photos.length - 1)));
            photos.forEach((photo, index) => {
              gsap.set(photo, { autoAlpha: index === activeIndex ? 1 : 0 });
            });
            updateActivePhoto(trigger.progress);
          }
        }));
        return;
      }

      const photoTimeline = gsap.timeline({
        scrollTrigger: {
          trigger: sequence,
          start: () => `top top+=${headerOffset}`,
          end: () => `+=${(photos.length - 1) * window.innerHeight}`,
          scrub: 0.7,
          invalidateOnRefresh: true,
          onUpdate: (trigger) => updateActivePhoto(trigger.progress)
        }
      });
      projectPhotoTimelines.push(photoTimeline);

      photos.slice(0, -1).forEach((photo, index) => {
        photoTimeline
          .to(photo, { autoAlpha: 0, duration: 0.18 }, index + 0.82)
          .to(photos[index + 1], { autoAlpha: 1, duration: 0.18 }, index + 0.82);
      });
    });
  };

  setupProjectPhotoSequences();
  window.addEventListener('resize', () => {
    setupProjectPhotoSequences();
    ScrollTrigger.refresh();
  });

  gsap.set('#h2-1', { opacity: 0 });
  gsap.set('#bg_grad', { attr: { cy: '-50' } });
  gsap.set(['#dinoL', '#dinoR'], { y: 80 });
  gsap.set('#dinoL', { x: -10 });
  gsap.set('#scene3', { y: sceneHeight - 40, visibility: 'visible' });

  const fireStage = document.querySelector('.fire-stage');
  if (fireStage) {
    gsap.to('#canvas', {
      autoAlpha: 0.45,
      scrollTrigger: {
        trigger: fireStage,
        start: 'top bottom',
        end: 'top top',
        scrub: 1
      }
    });
    gsap.to('#canvas', {
      autoAlpha: 0,
      scrollTrigger: {
        trigger: fireStage,
        start: 'bottom bottom',
        end: 'bottom top',
        scrub: 1
      }
    });
  }

  gsap.to(leafArt, {
    autoAlpha: 0.38,
    scrollTrigger: {
      trigger: scrollElement,
      start: '16% top',
      end: '30% top',
      scrub: 1
    }
  });
  gsap.to(leafArt, {
    autoAlpha: 0,
    scrollTrigger: {
      trigger: scrollElement,
      start: '62% top',
      end: '75% top',
      scrub: 1
    }
  });

  const leftLeaves = leafArt.querySelectorAll('.left [href="#leftLeave"]');
  leftLeaves.forEach((leaf, index) => {
    const progress = index / Math.max(leftLeaves.length - 1, 1);
    gsap.set(leaf, { rotate: progress * -35 + 8, transformOrigin: 'right bottom' });
    gsap.to(leaf, {
      x: 70,
      y: 95,
      rotate: 12 - Math.sin(progress * Math.PI) * 8,
      scale: 1.12,
      scrollTrigger: {
        trigger: scrollElement,
        start: '18% top',
        end: '64% top',
        scrub: 1
      }
    });
  });

  const rightLeaves = leafArt.querySelectorAll('.left [href="#rightLeave"]');
  rightLeaves.forEach((leaf, index) => {
    const progress = index / Math.max(rightLeaves.length - 1, 1);
    gsap.set(leaf, { rotate: progress * 35 - 8, transformOrigin: 'left bottom' });
    gsap.to(leaf, {
      x: -70,
      y: 95,
      rotate: -12 + Math.sin(progress * Math.PI) * 8,
      scale: 1.12,
      scrollTrigger: {
        trigger: scrollElement,
        start: '18% top',
        end: '64% top',
        scrub: 1
      }
    });
  });

  leafArt.querySelectorAll('.left [href="#smallLeaf"]').forEach((leaf, index) => {
    gsap.set(leaf, {
      x: (index % 2 === 0 ? 1 : -1) * (80 + index * 12),
      y: 50 + index * 15,
      scale: 0.55 + (index % 3) * 0.1,
      transformOrigin: '50% 50%'
    });
    gsap.to(leaf, {
      x: (index % 2 === 0 ? -1 : 1) * (130 + index * 8),
      y: -70 - index * 10,
      rotate: (index % 2 === 0 ? 1 : -1) * (150 + index * 17),
      scrollTrigger: {
        trigger: scrollElement,
        start: '18% top',
        end: '64% top',
        scrub: 1
      }
    });
  });

  gsap.utils.toArray('[data-reveal]').forEach((card) => {
    gsap.fromTo(card, { y: 35, opacity: 0.55 }, {
      y: 0,
      opacity: 1,
      scrollTrigger: {
        trigger: card,
        start: 'top 92%',
        end: 'top 60%',
        scrub: 0.6
      }
    });
  });

  document.querySelectorAll('[data-project-card]').forEach((project) => {
    const details = project.querySelector('.project-details');
    const memberButton = details?.querySelector('.members-open');
    const description = details?.querySelector('.project-description');

    if (details && (memberButton || description) && !prefersReducedMotion) {
      const reveal = gsap.timeline({
        scrollTrigger: {
          trigger: details,
          start: 'top 82%',
          toggleActions: 'play none none reverse'
        }
      });
      if (memberButton) {
        gsap.set(memberButton, { autoAlpha: 0, y: 18 });
        reveal.to(memberButton, { autoAlpha: 1, y: 0, duration: 0.35 });
      }
      if (description) {
        gsap.set(description, { autoAlpha: 0, y: 18 });
        reveal.to(description, { autoAlpha: 1, y: 0, duration: 0.45 }, memberButton ? '-=0.08' : 0);
      }
    }

    if (!prefersReducedMotion) {
      gsap.fromTo(project, { transformPerspective: 900 }, {
        y: -28,
        rotationX: -8,
        autoAlpha: 0.35,
        transformOrigin: 'top center',
        scrollTrigger: {
          trigger: project,
          start: 'bottom bottom',
          end: 'bottom top',
          scrub: 0.8
        }
      });
    }
  });

  if (!prefersReducedMotion) {
    import('https://esm.sh/lenis@1.3.11').then(({ default: Lenis }) => {
      if (typeof Lenis !== 'function') {
        throw new TypeError('Pustaka Lenis tidak menyediakan konstruktor yang valid.');
      }
      const lenis = new Lenis({ autoRaf: false, smoothWheel: true });
      lenis.on('scroll', ScrollTrigger.update);
      gsap.ticker.add((time) => lenis.raf(time * 1000));
      gsap.ticker.lagSmoothing(0);
    }).catch((error) => {
      console.error('Gelar Karya: Lenis smooth scroll gagal dimuat.', error);
    });
  }

  const mountainJourney = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: 'top top',
      end: '45% top',
      scrub: 2
    }
  });
  mountainJourney
    .to('#h1-1', { y: 300, x: 100, scale: 0.9, ease: 'power1.in' }, 0)
    .to('#h1-2', { y: 260, x: -60, ease: 'power1.in' }, 0)
    .to('#h1-3', { y: 170, x: 120 }, 0.03)
    .to('#h1-4', { y: 300, x: 100 }, 0.03)
    .to('#h1-5', { y: 200, x: 100 }, 0.03)
    .to('#h1-6', { y: 230, x: -250 }, 0)
    .to('#h1-7', { y: 500, x: 160 }, 0)
    .to('#h1-8', { y: 350, x: 20 }, 0)
    .to('#h1-9', { y: 350, x: -20 }, 0)
    .to('#cloudsBig-L', { y: 450, x: -20 }, 0)
    .to('#cloudsBig-R', { y: 450, x: -20 }, 0)
    .to('#cloudStart-L', { x: -300 }, 0)
    .to('#cloudStart-R', { x: 300 }, 0)
    .to('#info', { y: 800 }, 0);

  gsap.fromTo('#bird', { opacity: 1 }, {
    y: -250,
    x: 800,
    ease: 'power2.out',
    scrollTrigger: {
      trigger: scrollElement,
      start: '15% top',
      end: '60% 100%',
      scrub: 4,
      onEnter: () => gsap.to('#bird', { scaleX: 1, rotation: 0 }),
      onLeave: () => gsap.to('#bird', { scaleX: -1, rotation: -15 })
    }
  });

  const clouds = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: 'top top',
      end: '70% 100%',
      scrub: 1
    }
  });
  clouds
    .to('#cloud1', { x: 500 }, 0)
    .to('#cloud2', { x: 1000 }, 0)
    .to('#cloud3', { x: -1000 }, 0)
    .to('#cloud4', { x: -700, y: 25 }, 0);

  const sunset = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: '1% top',
      end: '2150 100%',
      scrub: 2
    }
  });
  sunset
    .fromTo('#bg_grad', { attr: { cy: '-50' } }, { attr: { cy: '330' } }, 0)
    .to('#bg_grad stop:nth-child(2)', { attr: { offset: '0.15' } }, 0)
    .to('#bg_grad stop:nth-child(3)', { attr: { offset: '0.18' } }, 0)
    .to('#bg_grad stop:nth-child(4)', { attr: { offset: '0.25' } }, 0)
    .to('#bg_grad stop:nth-child(5)', { attr: { offset: '0.46' } }, 0)
    .to('#bg_grad stop:nth-child(6)', { attr: { 'stop-color': '#FF9171' } }, 0);

  const forestScene = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: '15% top',
      end: '40% 100%',
      scrub: 3
    }
  });
  forestScene
    .fromTo('#h2-1', { y: 500, opacity: 0 }, { y: 0, opacity: 1 }, 0)
    .fromTo('#h2-2', { y: 500 }, { y: 0 }, 0.1)
    .fromTo('#h2-3', { y: 700 }, { y: 0 }, 0.1)
    .fromTo('#h2-4', { y: 700 }, { y: 0 }, 0.2)
    .fromTo('#h2-5', { y: 800 }, { y: 0 }, 0.3)
    .fromTo('#h2-6', { y: 900 }, { y: 0 }, 0.3);

  gsap.set('#bats', { transformOrigin: '50% 50%' });
  gsap.fromTo('#bats', { opacity: 1, y: 400, scale: 0 }, {
    y: 20,
    scale: 0.8,
    ease: 'power3.out',
    scrollTrigger: {
      trigger: scrollElement,
      start: '40% top',
      end: '70% 100%',
      scrub: 3,
      onEnter: () => {
        gsap.utils.toArray('#bats path').forEach((bat, index) => {
          gsap.to(bat, {
            scaleX: 0.5,
            yoyo: true,
            repeat: 9,
            transformOrigin: '50% 50%',
            duration: 0.15,
            delay: 0.7 + index / 10
          });
        });
        gsap.set('#bats', { opacity: 1 });
      }
    }
  });

  const nightSky = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: '2000 top',
      end: '5000 100%',
      scrub: 2
    }
  });
  nightSky
    .to('#sun', { attr: { offset: '1.4', 'stop-color': '#ffff00' } }, 0)
    .to('#bg_grad stop:nth-child(2)', { attr: { offset: '0.7' } }, 0)
    .to('#lg4 stop:nth-child(1)', { attr: { 'stop-color': '#623951' } }, 0)
    .to('#lg4 stop:nth-child(2)', { attr: { 'stop-color': '#261F36' } }, 0)
    .to('#bg_grad stop:nth-child(6)', { attr: { 'stop-color': '#45224A' } }, 0);

  const nightTransition = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: '60% top',
      end: 'bottom 100%',
      scrub: 3
    }
  });
  nightTransition
    .to('#h2-1', { y: -sceneHeight - 100, scale: 1.5, transformOrigin: '50% 50%' }, 0)
    .to('#bg_grad', { attr: { cy: '-80' } }, 0)
    .to('#bg2', { y: 0 }, 0);

  const finalScene = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: '70% 50%',
      end: 'bottom 100%',
      scrub: 3
    }
  });
  finalScene
    .fromTo('#h3-1', { y: 300 }, { y: -550 }, 0)
    .fromTo('#h3-2', { y: 800 }, { y: -550 }, 0.03)
    .fromTo('#h3-3', { y: 600 }, { y: -550 }, 0.06)
    .fromTo('#h3-4', { y: 800 }, { y: -550 }, 0.09)
    .fromTo('#h3-5', { y: 1000 }, { y: -550 }, 0.12)
    .fromTo('#stars', { opacity: 0 }, { opacity: 0.5, y: -500 }, 0)
    .fromTo('#arrow2', { opacity: 0 }, { opacity: 0.7, y: -710 }, 0.25)
    .fromTo('#text2', { opacity: 0 }, { opacity: 0.7, y: -710 }, 0.3)
    .to('#bg2-grad', { attr: { cy: 600, r: 500 } }, 0);
/* Interlude text scenes */
const interludeTexts = ["#interludeText1", "#interludeText2", "#interludeText3"];
const interludeTimeline = gsap.timeline({
    scrollTrigger: {
        trigger: ".scrollElement",
        start: "20% top",
        end: "63% top",
        scrub: 2
    }
});

interludeTexts.forEach((text, index) => {
    const position = index;
    interludeTimeline.fromTo(text, { opacity: 0, y: 18 }, { opacity: 1, y: 0, duration: 0.2, ease: "none" }, position);
    interludeTimeline.to(text, { opacity: 0, y: -18, duration: 0.2, ease: "none" }, position + 0.8);
});

  gsap.set('#fstar', { y: -400 });
  const fallingStar = gsap.timeline({
    scrollTrigger: {
      trigger: scrollElement,
      start: '4200 top',
      end: '6000 bottom',
      scrub: 2,
      onEnter: () => gsap.set('#fstar', { opacity: 1 }),
      onLeave: () => gsap.set('#fstar', { opacity: 0 })
    }
  });
  fallingStar.to('#fstar', { x: -700, y: -250, ease: 'power2.out' }, 0);

  document.querySelectorAll('#stars path:nth-of-type(1), #stars path:nth-of-type(3), #stars path:nth-of-type(5), #stars path:nth-of-type(8), #stars path:nth-of-type(11), #stars path:nth-of-type(15), #stars path:nth-of-type(17), #stars path:nth-of-type(18)').forEach((star, index) => {
    gsap.fromTo(star, { opacity: 0.3 }, {
      opacity: 1,
      duration: 0.3,
      repeat: -1,
      repeatDelay: 0.5 + (index % 5) * 0.3
    });
  });

  async function startFireShader() {
    const canvas = document.querySelector('#canvas');
    const container = document.querySelector('.fire-stage');
    const vertexShader = document.querySelector('#vertexShader')?.textContent;
    const fragmentShader = document.querySelector('#fragmentShader')?.textContent;
    if (!canvas || !container || !vertexShader || !fragmentShader) {
      console.error('Gelar Karya: elemen shader api tidak lengkap.');
      return;
    }

    const THREE = await import('https://esm.sh/three@0.133.1/build/three.module');
    const renderer = new THREE.WebGLRenderer({ alpha: true, canvas, antialias: false });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

    const scene = new THREE.Scene();
    const camera = new THREE.OrthographicCamera(-0.5, 0.5, 0.5, -0.5, 0.1, 10);
    camera.position.z = 1;
    const parameters = { speed: 2, shape: 1, power: 0.7, addition: 0.5 };
    const material = new THREE.ShaderMaterial({
      uniforms: {
        u_time: { type: 'f', value: 0 },
        u_ratio: { type: 'f', value: window.innerWidth / Math.max(window.innerHeight, 1) },
        u_speed: { type: 'f', value: parameters.speed },
        u_shape_offset: { type: 'f', value: parameters.shape },
        u_power: { type: 'f', value: parameters.power },
        u_addition: { type: 'f', value: parameters.addition }
      },
      vertexShader,
      fragmentShader,
      transparent: true
    });
    scene.add(new THREE.Mesh(new THREE.PlaneGeometry(2, 2), material));

    const resize = () => {
      const width = Math.max(window.innerWidth, 1);
      const height = Math.max(window.innerHeight, 1);
      material.uniforms.u_ratio.value = width / height;
      renderer.setSize(width, height);
    };
    resize();
    window.addEventListener('resize', resize);

    gsap.timeline({
      scrollTrigger: {
        trigger: fireStage,
        start: 'top bottom',
        end: 'bottom top',
        scrub: true
      },
      onUpdate: () => {
        material.uniforms.u_shape_offset.value = parameters.shape;
        material.uniforms.u_power.value = parameters.power;
        material.uniforms.u_addition.value = parameters.addition;
      }
    })
      .to(parameters, { duration: 0.7, shape: 0.4 })
      .to(parameters, { duration: 0.7, power: 0.4, ease: 'power3.in' }, 0)
      .fromTo(parameters, { addition: 0.5 }, { duration: 0.7, addition: 1, ease: 'power3.out' }, 0.3);

    const clock = new THREE.Clock();
    const render = () => {
      material.uniforms.u_time.value = clock.getElapsedTime();
      renderer.render(scene, camera);
      window.requestAnimationFrame(render);
    };
    render();
  }

  startFireShader().catch((error) => {
    console.error('Gelar Karya: animasi api gagal dimuat.', error);
  });

  window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
}
