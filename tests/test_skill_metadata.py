import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("skill_metadata", Path(__file__).resolve().parents[1] / "bin/validate-skills-metadata.py")
validator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(validator)


class MetadataTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="gframe-metadata-test-")
        self.skill = Path(self.temp.name) / "gframe-fixture"
        (self.skill / "agents").mkdir(parents=True)

    def tearDown(self):
        self.temp.cleanup()

    def check(self, text):
        (self.skill / "agents/openai.yaml").write_text(text, encoding="utf-8")
        return validator.validate(self.skill)

    def test_empty_mapping_and_unknown_fields_remain_valid(self):
        self.assertEqual([], self.check("{}\n"))
        self.assertEqual([], self.check("future: {setting: 1}\n"))

    def test_invalid_yaml_sequence_and_duplicate_keys_fail(self):
        for text in ("interface: [", "- item\n", "policy: {}\npolicy: {}\n", "interface: {display_name: A, display_name: B}"):
            with self.subTest(text=text):
                self.assertTrue(self.check(text))

    def test_policy_boolean_preserves_explicit_false(self):
        self.assertEqual([], self.check("policy: {allow_implicit_invocation: false}"))
        self.assertTrue(self.check('policy: {allow_implicit_invocation: "false"}'))

    def test_optional_interface_values_are_checked_only_when_present(self):
        self.assertEqual([], self.check("interface: {}"))
        valid = 'interface:\n  short_description: "A description with enough characters"\n  default_prompt: "Use $gframe-fixture for this task."\n  brand_color: "#00Aabb"\n'
        self.assertEqual([], self.check(valid))
        for text in ('interface: {display_name: 42}', 'interface: {short_description: "short"}', 'interface: {default_prompt: "Use another skill"}', 'interface: {default_prompt: "Use $gframe-fixture-extra"}', 'interface: {brand_color: "blue"}', 'interface: []'):
            with self.subTest(text=text):
                self.assertTrue(self.check(text))

    def test_icons_cannot_escape_skill_and_must_exist(self):
        (self.skill / "assets").mkdir()
        (self.skill / "assets/icon.svg").write_text("<svg/>")
        (Path(self.temp.name) / "outside.svg").write_text("<svg/>")
        self.assertEqual([], self.check('interface: {icon_small: "./assets/icon.svg"}'))
        self.assertTrue(self.check('interface: {icon_small: "./../outside.svg"}'))
        self.assertTrue(self.check('interface: {icon_large: "./assets/missing.svg"}'))

    def test_dependencies_require_list_and_mcp_identity(self):
        self.assertEqual([], self.check('dependencies: {tools: [{type: mcp, value: github}]}'))
        for text in ('dependencies: {tools: {}}', 'dependencies: {tools: [{type: other, value: github}]}', 'dependencies: {tools: [{type: mcp}]}'):
            with self.subTest(text=text):
                self.assertTrue(self.check(text))

    def test_missing_and_invalid_utf8_metadata_fail(self):
        self.assertTrue(validator.validate(self.skill))
        (self.skill / "agents/openai.yaml").write_bytes(b"\xff")
        self.assertTrue(validator.validate(self.skill))


if __name__ == "__main__":
    unittest.main()
