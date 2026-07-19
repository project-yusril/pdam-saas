import tempfile
import unittest
from unittest.mock import patch

from scripts.generate_validation_artifacts import main


class GenerateValidationArtifactsScriptTest(unittest.TestCase):
    def test_main_delegates_cli_paths_to_core_generator(self):
        expected = {"artifact_kind": "fixture_validation"}
        with tempfile.TemporaryDirectory() as directory, patch(
            "scripts.generate_validation_artifacts.generate_validation_artifacts",
            return_value=expected,
        ) as generate:
            self.assertEqual(main(["--config", "config.yml", "--output-dir", directory]), expected)
        generate.assert_called_once_with("config.yml", directory, None)

    def test_main_does_not_hide_generation_failures(self):
        with patch(
            "scripts.generate_validation_artifacts.generate_validation_artifacts",
            side_effect=RuntimeError("model failed"),
        ):
            with self.assertRaisesRegex(RuntimeError, "model failed"):
                main([])


if __name__ == "__main__":
    unittest.main()
