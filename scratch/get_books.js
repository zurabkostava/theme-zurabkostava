const https = require('https');
const links = [
    'https://www.goodreads.com/book/show/27833670-dark-matter',
    'https://www.goodreads.com/book/show/20518872-the-three-body-problem',
    'https://www.goodreads.com/book/show/5129.Brave_New_World',
    'https://www.goodreads.com/book/show/10569269'
];

function fetch(url) {
    return new Promise(r => {
        https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36', 'Accept': 'text/html' } }, res => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                return r(fetch(res.headers.location));
            }
            let d = '';
            res.on('data', c => d += c);
            res.on('end', () => r(d));
        }).on('error', () => r(''));
    });
}

async function run() {
    for (let link of links) {
        let html = await fetch(link);
        let m = html.match(/<meta[^>]*property=['"]og:image['"][^>]*content=['"]([^'"]+)['"]/i);
        if (!m) m = html.match(/<meta[^>]*content=['"]([^'"]+)['"][^>]*property=['"]og:image['"]/i);
        console.log(`'${link}' => '${m ? m[1] : 'FAIL'}',`);
    }
}
run();
