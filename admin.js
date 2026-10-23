const memberTemplate = document.querySelector('#project-member-template');

document.querySelectorAll('[data-project-form]').forEach((form) => {
  const photoInput = form.querySelector('.project-photo-input');
  const photoPreview = form.querySelector('.project-photo-preview');

  if (photoInput && photoPreview) {
    photoInput.addEventListener('change', () => {
      photoPreview.replaceChildren();
      const files = Array.from(photoInput.files || []);
      if (files.length > 6) {
        photoInput.value = '';
        photoPreview.textContent = 'Pilih maksimal enam foto.';
        return;
      }

      files.forEach((file) => {
        if (!file.type.startsWith('image/')) return;
        const image = document.createElement('img');
        image.alt = file.name;
        image.src = URL.createObjectURL(file);
        image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
        photoPreview.append(image);
      });
    });
  }

  const projectMembers = form.querySelector('.project-members');
  const addMemberButton = form.querySelector('.add-project-member');
  if (!projectMembers || !addMemberButton || !memberTemplate) return;

  let nextIndex = Number(projectMembers.dataset.nextIndex || 0);
  const setupMemberCard = (card) => {
    const photoField = card.querySelector('input[type="file"]');
    const preview = card.querySelector('[data-member-preview]');
    photoField.addEventListener('change', () => {
      preview.replaceChildren();
      const [file] = photoField.files || [];
      if (!file || !file.type.startsWith('image/')) return;

      const image = document.createElement('img');
      image.alt = `Pratinjau foto ${file.name}`;
      image.src = URL.createObjectURL(file);
      image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
      preview.append(image);
    });
    card.querySelector('[data-remove-member]').addEventListener('click', () => {
      card.remove();
      updateMemberCount();
    });
  };

  const updateMemberCount = () => {
    const memberCards = Array.from(projectMembers.children);
    memberCards.forEach((card, index) => {
      card.querySelector('[data-member-number]').textContent = String(index + 1).padStart(2, '0');
    });
    addMemberButton.querySelector('span').textContent = `(${memberCards.length}/12)`;
    addMemberButton.disabled = memberCards.length >= 12;
  };

  projectMembers.querySelectorAll('.member-form-card').forEach(setupMemberCard);
  addMemberButton.addEventListener('click', () => {
    if (projectMembers.children.length >= 12) return;

    const index = nextIndex++;
    const memberCard = memberTemplate.content.cloneNode(true);
    const formKey = form.dataset.formKey || 'project';
    memberCard.querySelectorAll('[id], [for], [name]').forEach((element) => {
      ['id', 'for', 'name'].forEach((attribute) => {
        if (element.hasAttribute(attribute)) {
          element.setAttribute(
            attribute,
            element.getAttribute(attribute).replaceAll('__INDEX__', String(index)).replaceAll('__FORM__', formKey),
          );
        }
      });
    });
    const card = memberCard.querySelector('.member-form-card');
    setupMemberCard(card);
    projectMembers.append(memberCard);
    updateMemberCount();
  });

  updateMemberCount();
});

document.querySelectorAll('[data-toggle-edit]').forEach((button) => {
  button.addEventListener('click', () => {
    const editForm = button.closest('.saved-project-item').querySelector('.project-edit-form');
    editForm.hidden = !editForm.hidden;
    button.setAttribute('aria-expanded', String(!editForm.hidden));
  });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm || 'Lanjutkan tindakan ini?')) {
      event.preventDefault();
    }
  });
});
