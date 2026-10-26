// Before/after slider (resources/views/components/site/before-after.blade.php): the range input is
// the accessible control; mirror its value into the --position the clip-path and handle use.
for (const input of document.querySelectorAll('[data-before-after]')) {
    const figure = input.closest('.before-after');
    const update = () => figure.style.setProperty('--position', `${input.value}%`);

    input.addEventListener('input', update);
    update();
}
