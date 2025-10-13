export default [
  {
    files: ["assets/**/*.js"],
    rules: {
      "quotes": ["error", "double"],
      "semi": ["error", "always"],
      "no-unused-vars": "warn",
      "indent": ["error", 2]
    },
    languageOptions: {
      parserOptions: {
        ecmaVersion: 2021,
        sourceType: "module"
      },
      globals: {
        window: "readonly",
        document: "readonly"
      }
    }
  }
];