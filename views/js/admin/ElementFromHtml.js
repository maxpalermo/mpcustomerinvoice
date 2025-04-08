function ElementFromHtml(html) {
    const template = document.createElement("template");
    template.innerHTML = html.trim();
    const element = template.content.firstElementChild;
    template.remove();
    return element;
}

function testElementFromHtml() {
    const html = `
    <div class="test">
        <p>Test</p>
    </div>
    `;
    const element = ElementFromHtml(html);
    return element;
}
