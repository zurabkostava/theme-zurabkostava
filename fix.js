const fs = require('fs');
const files = {
  'makeword.js': 'function showMakewordResults() {',
  'mix.js': 'function showMixResults() {',
  'puzzle.js': 'function showPuzzleResults() {',
  'quiz.js': 'function showQuizResult() {',
  'sentence.js': 'function showSentenceResult() {',
  'speakgame.js': 'function showSpeakResult() {',
  'typegame.js': 'function showTypingResult() {',
  'wordhear.js': 'function showWordhearResults() {'
};
for (const [file, func] of Object.entries(files)) {
  let content = fs.readFileSync('WordEvo/games/' + file, 'utf8');
  content = content.replace('    if(window.setGlobalGameRunning) window.setGlobalGameRunning(false);', func + '\n    if(window.setGlobalGameRunning) window.setGlobalGameRunning(false);');
  fs.writeFileSync('WordEvo/games/' + file, content);
  console.log('Fixed ' + file);
}

