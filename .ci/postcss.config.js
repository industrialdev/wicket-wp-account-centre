module.exports = {
  plugins: [
    require('postcss-prefixer')({
      prefix: '.wicket',
      // Exclude already scoped rules and keyframes
      ignore: [/:root/, /@keyframes/, /^\.wicket/]
    })
  ]
};
